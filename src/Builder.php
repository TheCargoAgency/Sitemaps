<?php

namespace Cargo\Sitemap;

class Builder
{
    private $xml;
    private $changeFrequency = 'monthly'; // always, hourly, daily, weekly, monthly, yearly, never
    private $protocol = 'http'; // sitemaps must have same protocol (mix of http & https is concidered invalid)

    public function __construct()
    {

    }

    public function build($urls)
    {
        $this->xml = new \DOMDocument('1.0', 'UTF-8');
        $this->xml->preserveWhiteSpace = false;
        $this->xml->formatOutput = true;

        $urlsetNode = $this->xml->createElement('urlset');
        $urlsetNode->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $this->xml->appendChild($urlsetNode);

        foreach ($urls as $url) {

            $urlNode = $this->xml->createElement('url');

            $locNode = $this->xml->createElement('loc');
            $escapedUrl = $this->xml->createTextNode($this->protocol.':'.Fetcher::formatUrl($url));
            $locNode->appendChild($escapedUrl);
            $urlNode->appendChild($locNode);

            $lastmodNode = $this->xml->createElement('lastmod', date('Y-m-d'));
            $urlNode->appendChild($lastmodNode);

            $changefreqNode = $this->xml->createElement('changefreq', $this->changeFrequency);
            $urlNode->appendChild($changefreqNode);

            $priorityNode = $this->xml->createElement('priority', $this->getUrlPriority($url));
            $urlNode->appendChild($priorityNode);

            $this->xml->appendChild($urlNode);
        }

        return $this->xml->saveXML();
    }

    public function setChangeFrequency($changeFrequency)
    {
        $this->changeFrequency = $changeFrequency;
    }

    public function setProtocol($protocol)
    {
        $this->protocol = $protocol;
    }

    protected function getUrlPriority($url)
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (empty($path) || '/' == $path) {
            return 1;
        }

        $dirs = explode('/', $path);
        $dirsNum = count($dirs) - 1;
        if ($dirsNum > 9) {
            $dirsNum = 9;
        }

        return 1 - $dirsNum / 10;
    }
}
