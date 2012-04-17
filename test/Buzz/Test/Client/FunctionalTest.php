<?php

namespace Buzz\Test\Client;

use Buzz\Client\Curl;
use Buzz\Client\FileGetContents;
use Buzz\Message\FormRequest;
use Buzz\Message\FormUpload;
use Buzz\Message\Request;
use Buzz\Message\Response;

class FunctionalTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provideProxyClientAndUrl
     */
    public function testProxy($client, $proxy, $url)
    {
        $client = $this->createClient($client, $proxy);

        $request = new Request();
        $request->fromUrl($url);
        $response = new Response();

        $client->send($request, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertInternalType('array', $data);
        $this->assertArrayHasKey('HTTP_VIA', $data['SERVER']);
    }

    /**
     * @dataProvider provideClientAndUrlAndMethod
     */
    public function testRequestMethods($client, $proxy, $url, $method)
    {
        $client = $this->createClient($client, $proxy);

        $request = new Request($method);
        $request->fromUrl($url);
        $response = new Response();

        $client->send($request, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals($method, $data['SERVER']['REQUEST_METHOD']);
    }

    /**
     * @dataProvider provideClientAndUrl
     */
    public function testFormPost($client, $proxy, $url)
    {
        $client = $this->createClient($client, $proxy);

        $request = new FormRequest();
        $request->fromUrl($url);
        $request->setField('company[name]', 'Google');
        $response = new Response();
        $client->send($request, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertStringStartsWith('application/x-www-form-urlencoded', $data['SERVER']['CONTENT_TYPE']);
        $this->assertEquals('Google', $data['POST']['company']['name']);
    }

    /**
     * @dataProvider provideClientAndUrlAndUpload
     */
    public function testFileUpload($client, $proxy, $url, $upload)
    {
        $client = $this->createClient($client, $proxy);

        $request = new FormRequest();
        $request->fromUrl($url);
        $request->setField('company[name]', 'Google');
        $request->setField('company[logo]', $upload);
        $response = new Response();
        $client->send($request, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertStringStartsWith('multipart/form-data', $data['SERVER']['CONTENT_TYPE']);
        $this->assertEquals('Google', $data['POST']['company']['name']);
        $this->assertEquals('google.png', $data['FILES']['company']['name']['logo']);
    }

    /**
     * @dataProvider provideClientAndUrl
     */
    public function testJsonPayload($client, $proxy, $url)
    {
        $client = $this->createClient($client, $proxy);

        $request = new Request(Request::METHOD_POST);
        $request->fromUrl($url);
        $request->addHeader('Content-Type: application/json');
        $request->setContent(json_encode(array('foo' => 'bar')));
        $response = new Response();
        $client->send($request, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals('application/json', $data['SERVER']['CONTENT_TYPE']);
        $this->assertEquals('{"foo":"bar"}', $data['INPUT']);
    }

    /**
     * @dataProvider provideClientAndUrl
     */
    public function testConsecutiveRequests($client, $proxy, $url)
    {
        $client = $this->createClient($client, $proxy);

        // request 1
        $request = new Request(Request::METHOD_PUT);
        $request->fromUrl($url);
        $request->addHeader('Content-Type: application/json');
        $request->setContent(json_encode(array('foo' => 'bar')));
        $response = new Response();
        $client->send($request, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals('PUT', $data['SERVER']['REQUEST_METHOD']);
        $this->assertEquals('application/json', $data['SERVER']['CONTENT_TYPE']);
        $this->assertEquals('{"foo":"bar"}', $data['INPUT']);

        // request 2
        $request = new Request(Request::METHOD_GET);
        $request->fromUrl($_SERVER['TEST_SERVER']);
        $response = new Response();
        $client->send($request, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals('GET', $data['SERVER']['REQUEST_METHOD']);
        $this->assertEmpty($data['INPUT']);
    }

    /**
     * @dataProvider provideClientAndUrl
     */
    public function testPlus($client, $proxy, $url)
    {
        $client = $this->createClient($client, $proxy);

        $request = new FormRequest();
        $request->fromUrl($url);
        $request->setField('math', '1+1=2');
        $response = new Response();
        $client->send($request, $response);

        $data = json_decode($response->getContent(), true);
        parse_str($data['INPUT'], $fields);

        $this->assertEquals(array('math' => '1+1=2'), $fields);
    }

    // internal

    public function provideClientAndUrl()
    {
        return $this->cartesian($this->getClients(), $this->getProxies(), $this->getUrls());
    }

    public function provideProxyClientAndUrl()
    {
        return $this->cartesian($this->getClients(), $this->getProxies(false), $this->getUrls());
    }

    public function provideClientAndUrlAndMethod()
    {
        // HEAD is intentionally omitted
        // http://stackoverflow.com/questions/2603104/does-mod-php-honor-head-requests-properly
        $methods = array('GET', 'POST', 'PUT', 'DELETE', 'PATCH');

        return $this->cartesian($this->getClients(), $this->getProxies(), $this->getUrls(), $methods);
    }

    public function provideClientAndUrlAndUpload()
    {
        $uploads = array();
        $uploads[0] = new FormUpload();
        $uploads[0]->setFilename('google.png');
        $uploads[0]->setContent(file_get_contents(__DIR__.'/../Message/Fixtures/google.png'));
        $uploads[1] = new FormUpload(__DIR__.'/../Message/Fixtures/google.png');

        return $this->cartesian($this->getClients(), $this->getProxies(), $this->getUrls(), $uploads);
    }

    // private

    private function createClient($class, $proxy)
    {
        $class = 'Buzz\Client\\'.$class;

        $client = new $class();
        $client->setVerifyPeer(false);

        if ($proxy) {
            $client->setProxy($_SERVER[$proxy]);
        }

        return $client;
    }

    private function getClients()
    {
        return array(
            'Curl',
            'FileGetContents',
        );
    }

    private function getProxies($includeEmpty = true)
    {
        $proxies = array_filter(array(
            'TEST_PROXY',
            'TEST_PROXY_AUTH',
            'TEST_PROXY_SSL',
            'TEST_PROXY_SSL_AUTH',
        ), function($value) { return isset($_SERVER[$value]); });

        if ($includeEmpty) {
            $proxies[] = null;
        }

        return $proxies;
    }

    private function getUrls()
    {
        $servers = array();

        if (isset($_SERVER['TEST_SERVER'])) {
            $servers[] = $_SERVER['TEST_SERVER'];
        }

        if (isset($_SERVER['TEST_SERVER_SSL'])) {
            $servers[] = $_SERVER['TEST_SERVER_SSL'];
        }

        if (!$servers) {
            $this->markTestSkipped('There are no test servers configured.');
        }

        return $servers;
    }

    /**
     * @see http://stackoverflow.com/questions/6311779/finding-cartesian-product-with-php-associative-arrays
     */
    private function cartesian()
    {
        $inputs = func_get_args();
        $result = array();
        while (list($key, $values) = each($inputs)) {
            if (empty($values)) {
                throw new \Exception('The '.$key.' value is empty.');
            }

            if (empty($result)) {
                foreach($values as $value) {
                    $result[] = array($key => $value);
                }
            } else {
                $append = array();
                foreach ($result as & $product) {
                    $product[$key] = array_shift($values);
                    $copy = $product;

                    foreach ($values as $item) {
                        $copy[$key] = $item;
                        $append[] = $copy;
                    }

                    array_unshift($values, $product[$key]);
                }

                $result = array_merge($result, $append);
            }
        }

        return $result;
    }
}
