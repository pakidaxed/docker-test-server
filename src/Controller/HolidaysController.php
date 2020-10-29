<?php


namespace App\Controller;


use App\Entity\Holidays;
use App\Form\SearchType;
use App\Service\YearSorting;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HolidaysController extends AbstractController
{
    private $client;

    private $country;
    private $year;
    private $content;
    private $source;
    private $total_free_days;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * @Route("/holidays", name="app_holidays")
     * @return Response
     */
    public function getHolidays(Request $request)
    {
        $form = $this->createForm(SearchType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            return $this->redirectToRoute('app_holidays_show', ['data' => $data]);
        }

        return $this->render('holidays/index.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/holidays/show/", name="app_holidays_show")
     * @param Request $request
     * @param YearSorting $sorting
     * @param EntityManagerInterface $entity
     * @return Response
     */
    public function showHolidays(Request $request, YearSorting $sorting, EntityManagerInterface $entity): Response
    {
        $data = $request->get('data');
        $this->country = $data['country'];
        $this->year = $data['year'];

        $repo = $entity->getRepository(Holidays::class);
        $db_data = $repo->findOneBy(['country' => $this->country, 'year' => $this->year]);
        if ($db_data) {
            $this->source = 'Database';
            $this->content = $db_data->getData();
            $this->total_free_days = $db_data->getFree();
        } else {
            $this->source = 'API';

            $response = $this->client->request(
                'GET',
                'https://kayaposoft.com/enrico/json/v2.0/', [
                    'query' => [
                        'action' => 'getHolidaysForYear',
                        'year' => $this->year,
                        'country' => $this->country,
                        'holidayType' => 'all'
                    ]
                ]
            );

            if ($response->getStatusCode() === 200
                && $response->getHeaders()['content-type'][0] === 'application/json') {

                $response->getContent();
                $this->content = $response->toArray();
                $this->total_free_days = $this->totalFreeDays($this->content);

                $holidays = new Holidays();
                $holidays->setYear($this->year);
                $holidays->setCountry($this->country);
                $holidays->setData($this->content);
                $holidays->setFree($this->totalFreeDays($this->content));

                $entity->persist($holidays);
                $entity->flush();
            }
        }

        $holidays_total = count($this->content);
        $sorted_data = $sorting->sortingToMonths($this->content);
        $today_status = $this->getStatusToday($data['country']);

        return $this->render('holidays/show.html.twig', [
                'holidays_total' => $holidays_total,
                'today_status' => $today_status,
                'content' => $sorted_data,
                'total_free_days' => $this->total_free_days,
                'source' => $this->source
            ]
        );
    }

    //statusas irgi ryja milisekundes
    public function getStatusToday($country)
    {
        $response = $this->client->request(
            'GET',
            'https://kayaposoft.com/enrico/json/v2.0/', [
            'query' => [
                'action' => 'isPublicHoliday',
                'date' => date('d-m-Y'),
                'country' => $country,
            ]
        ]);
        $response->getContent();
        $public_holiday = $response->toArray();

        $response2 = $this->client->request(
            'GET',
            'https://kayaposoft.com/enrico/json/v2.0/', [
            'query' => [
                'action' => 'isWorkDay',
                'date' => date('d-m-Y'),
                'country' => $country,
            ]
        ]);
        $response2->getContent();
        $workday = $response2->toArray();

        if ($public_holiday['isPublicHoliday']) {

            $status = 'Public Holiday';

        } else if ($workday['isWorkDay']) {

            $status = 'Workday';

        } else {

            $status = 'Free day';

        }

        return 'Today is ' . $status;
    }

    // cia aprasyti komentara kad buvau padares kas karta is db ziuret, kad taupyt load time perkeliau i db total iseigines
    public function totalFreeDays($content)
    {
        $array = [];
        foreach ($content as $public_holidays) {
            $month = $public_holidays['date']['month'];
            $day = $public_holidays['date']['day'];

            $date = new DateTime($this->year . '-' . $month . '-' . $day);
            $temp_before = 0;
            $workday = false;
            while (!$workday) {
                $date->modify('- 1 Day');
                $response = $this->client->request(
                    'GET',
                    'https://kayaposoft.com/enrico/json/v2.0/', [
                    'query' => [
                        'action' => 'isWorkDay',
                        'date' => $date->format('d-m-Y'),
                        'country' => $this->country,
                    ]
                ]);
                $response->getContent();
                $workday = $response->toArray()['isWorkDay'];

                if ($workday) break;

                $temp_before++;
            }

            $date = new DateTime($this->year . '-' . $month . '-' . $day);
            $temp_after = 0;
            $workday = false;
            while (!$workday) {
                $date->modify('+ 1 Day');
                $response = $this->client->request(
                    'GET',
                    'https://kayaposoft.com/enrico/json/v2.0/', [
                    'query' => [
                        'action' => 'isWorkDay',
                        'date' => $date->format('d-m-Y'),
                        'country' => $this->country,
                    ]
                ]);
                $response->getContent();
                $workday = $response->toArray()['isWorkDay'];

                if ($workday) break;

                $temp_after++;
            }

            $array[] = $temp_before + 1 + $temp_after;
        }

        return max($array);
    }
}