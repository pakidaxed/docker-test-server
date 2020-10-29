<?php


namespace App\Tests\Controller;


use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DefaultControllerTest extends WebTestCase
{
    /**
     * Simple test crawling trough the page and checking for expected content
     * Checking for existing lings
     *
     */
    public function testIndex()
    {
        $client = $this->createClient();
        $crawler = $client->request('GET', '/');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertSelectorTextContains('html h1.title', 'HolidayAPP');

        $link = $crawler->selectLink('Search Holidays')->link();
        $client->click($link);

        $crawler = $client->submitForm('Search', [
            'search[country]' => 'ltu',
            'search[year]' => 2020
        ]);

        $this->assertEquals(302, $client->getResponse()->getStatusCode());
        $this->assertResponseRedirects('/holidays/show/?data%5Bcountry%5D=ltu&data%5Byear%5D=2020');

        $crawler = $client->request('GET', '/holidays/show/?data%5Bcountry%5D=ltu&data%5Byear%5D=2020');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertSelectorTextContains('html h1.header', 'RESULTS');

    }

    /**
     * @dataProvider Urls
     * @param $url
     */
    public function testPageExists($url)
    {
        $client = self::createClient();
        $client->request('GET', $url);

        $this->assertTrue($client->getResponse()->isSuccessful());
    }

    public function Urls()
    {
        return [
            ['/'],
            ['/holidays'],
        ];
    }
}