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
     * Creating the main search form to choose the country
     * and sending the choices to results page
     *
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
     * Getting data from inputs, connecting to DB to check for existing data
     * If there is no data, connect to API and get from there
     * After getting info from API, saving it to our DB for faster results next time
     * Also saving to data the maximum number of free days in a row, for faster rendering too
     * Because all connections to API icreasing the render time
     *
     * @Route("/holidays/show/", name="app_holidays_show")
     * @param Request $request
     * @param YearSorting $sorting
     * @param EntityManagerInterface $entity
     * @return Response
     */
    public function showHolidays(Request $request, YearSorting $sorting, EntityManagerInterface $entity): Response
    {
        // Taking data from input
        $data = $request->get('data');
        $this->country = $data['country'];
        $this->year = $data['year'];

        // Choosing what to do, DB or API
        $repo = $entity->getRepository(Holidays::class);
        $db_data = $repo->findOneBy(['country' => $this->country, 'year' => $this->year]);
        if ($db_data) {
            $this->source = 'database';
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

            // Saving to our DB  if everything is ok, and we dont have it in our DB
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

        // Setting some variables just for cleaner code
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

    /**
     * Getting status of current day, also requesting API (slows the render)
     * Maybe it could be set to the user cookie or server session, also to increase render time
     * Not to request every time, loading the results
     *
     * @param $country
     * @return string
     */
    public function getStatusToday($country)
    {
        // Checking today for public holiday ?
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

        // Checking today for workday
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

        // Setting the status of current day and returning it
        if ($public_holiday['isPublicHoliday']) {

            $status = 'Public Holiday';

        } else if ($workday['isWorkDay']) {

            $status = 'Workday';

        } else {

            $status = 'Free day';

        }

        return 'Today is ' . $status;
    }

    /**
     * Counting total free days in a row
     * Counting each public holiday before and after for free days or other holidays
     * until gets the workday
     * I decided to put it to the database too because it's the main load time consumer
     *
     * @param $content
     * @return mixed
     */
    public function totalFreeDays($content)
    {
        $array = [];
        foreach ($content as $public_holidays) {
            $month = $public_holidays['date']['month'];
            $day = $public_holidays['date']['day'];

            // Checking for all free days before the given date
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

            // Checking for all free days after the given date
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

            $total_in_a_row[] = $temp_before + 1 + $temp_after;
        }

        return max($total_in_a_row);
    }
}