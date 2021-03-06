<?php

namespace ApiBundle\Tests\Controller;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BreweryApiControllerTest extends WebTestCase
{
    public static function setUpBeforeClass()
    {
        $client = static::createClient();
        $em = $client->getContainer()
                     ->get('doctrine')
                     ->getManager();

        $loader = new Loader();
        $loader->loadFromDirectory('src/Maxpou/BeerBundle/DataFixtures');

        $purger = new ORMPurger();
        $executor = new ORMExecutor($em, $purger);
        $executor->execute($loader->getFixtures());
    }

    public function testGetCollection()
    {
        $client = static::createClient();

        $client->request('GET', '/api/breweries');

        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent());
    }

    public function testOptions()
    {
        $client = static::createClient();

        $client->request('OPTIONS', '/api/breweries');

        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent());
        $this->assertEquals($response->headers->get('allow'), 'OPTIONS, GET, POST, DELETE');
    }

    public function testPost()
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/breweries',
            array(),
            array(),
            array('CONTENT_TYPE' => 'application/json'),
            '{"name":"Grimbergen"}'
        );

        $response = $client->getResponse();
        $this->assertEquals(201, $response->getStatusCode(), $response->getContent());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $generatedBreweryId = $data['id'];

        //test fail
        $client->request(
            'POST',
            '/api/breweries',
            array(),
            array(),
            array('CONTENT_TYPE' => 'application/json'),
            '{"name":"Chimay"}'
        );

        $response = $client->getResponse();
        $this->assertEquals(400, $response->getStatusCode(), $response->getContent());
        $this->assertContains('This value is already used.', $response->getContent(), $response->getContent());

        return $generatedBreweryId;
    }

    /**
     * Test: 400, 404 and 204.
     *
     * @depends testPost
     */
    public function testPut($generatedBreweryId)
    {
        $client = static::createClient();

        //Fail (mandatory field)
        $client->request(
            'PUT',
            '/api/breweries/'.$generatedBreweryId,
            array(),
            array(),
            array('CONTENT_TYPE' => 'application/json'),
            '{
                "description":"Ardet nec consumitur"
            }'
        );

        $response = $client->getResponse();
        $this->assertEquals(400, $response->getStatusCode(), $response->getContent());
        $this->assertContains('This value should not be blank.', $response->getContent(), $response->getContent());

        //Fail (mandatory field)
        $client->request(
            'PUT',
            '/api/breweries/ThisBreweryDoesNotExist',
            array(),
            array(),
            array('CONTENT_TYPE' => 'application/json'),
            '{
                "description":"Ardet nec consumitur"
            }'
        );

        $response = $client->getResponse();
        $this->assertEquals(404, $response->getStatusCode(), $response->getContent());
        $this->assertContains('Unable to find this Brewery entity', $response->getContent(), $response->getContent());

        $client->request(
            'PUT',
            '/api/breweries/'.$generatedBreweryId,
            array(),
            array(),
            array('CONTENT_TYPE' => 'application/json'),
            '{
                "name":"Grimbergen Brewery",
                "description":"Ardet nec consumitur"
            }'
        );

        $response = $client->getResponse();
        $this->assertEquals(204, $response->getStatusCode(), $response->getContent());
    }

    /**
     * @depends testPost
     */
    public function testGet($generatedBreweryId)
    {
        $client = static::createClient();

        $client->request('GET', '/api/breweries/ThisBreweryDoesNotExist');

        $response = $client->getResponse();
        $this->assertEquals(404, $response->getStatusCode(), $response->getContent());
        $data = json_decode($response->getContent(), true);
        $this->assertContains('Unable to find this Brewery entity', $response->getContent(), $response->getContent());

        $client->request('GET', '/api/breweries/'.$generatedBreweryId);

        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Grimbergen Brewery', $data['name'], $response->getContent());
        $this->assertEquals('Ardet nec consumitur', $data['description'], $response->getContent());
        $this->assertArrayHasKey('_links', $data);
        $this->assertArrayHasKey('self', $data['_links']);
        $this->assertArrayHasKey('beers', $data['_links']);
    }

    /**
     * @depends testPost
     */
    public function testDelete($generatedBreweryId)
    {
        $client = static::createClient();

        $client->request('DELETE', '/api/breweries/'.$generatedBreweryId);

        $response = $client->getResponse();
        $this->assertEquals(204, $response->getStatusCode(), $response->getContent());

        //Test delete operation
        $client->request('GET', '/api/breweries/'.$generatedBreweryId);

        $response = $client->getResponse();
        $this->assertEquals(404, $response->getStatusCode(), $response->getContent());
    }

    public function testDeleteCollection()
    {
        $client = static::createClient();

        $client->request('DELETE', '/api/breweries');

        $response = $client->getResponse();
        $this->assertEquals(204, $response->getStatusCode(), $response->getContent());

        //Ensure
        $client->request('GET', '/api/breweries');

        $response = $client->getResponse();
        $data = json_decode($response->getContent(), true);
        $this->assertEquals([], $data, $response->getContent());
    }
}
