<?php

namespace Buzz\Client;

use Buzz\Message;
use Buzz\Util;

abstract class AbstractStream extends AbstractClient
{
    /**
     * Converts a request into an array for stream_context_create().
     *
     * @param Message\Request $request A request object
     *
     * @return array An array for stream_context_create()
     */
    public function getStreamContextArray(Message\Request $request)
    {
        $options = array(
            'http' => array(
                // values from the request
                'method'           => $request->getMethod(),
                'header'           => implode("\r\n", $request->getHeaders()),
                'content'          => $request->getContent(),
                'protocol_version' => $request->getProtocolVersion(),

                // values from the current client
                'ignore_errors'    => $this->getIgnoreErrors(),
                'max_redirects'    => $this->getMaxRedirects(),
                'timeout'          => $this->getTimeout(),
            ),
            'ssl' => array(
                'verify_peer'      => $this->getVerifyPeer(),
            ),
        );

        if ($proxy = $this->getProxy()) {
            $options['http']['proxy'] = self::getProxyOption($proxy);
            $options['http']['request_fulluri'] = true;
        }

        return $options;
    }

    /**
     * Converts an URL to a proxy string option.
     *
     * @param Url $url The proxy URL
     *
     * @return string The proxy option
     */
    static private function getProxyOption(Util\Url $url)
    {
        static $schemeMap = array(
            'http'  => 'tcp',
            'https' => 'ssl',
        );

        $scheme = $url->getScheme();
        $proxy  = isset($schemeMap[$scheme]) ? $schemeMap[$scheme] : $scheme;
        $proxy .= '://';

        if ($user = $url->getUser()) {
            $proxy .= $user.':'.$url->getPassword().'@';
        }

        $proxy .= $url->getHostname();
        $proxy .= ':';
        $proxy .= $url->getPort();

        return $proxy;
    }
}
