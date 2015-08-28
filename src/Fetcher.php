<?php

namespace Cargo\Sitemap;

use Goutte\Client;

class Fetcher
{
    private $client = null;
    private $baseUrl = null;
    private $exludeExtensions = [];
    private $excludeFiles = [];
    private $excludeDirs = [];
    private $links = [];
    private $maxCrawlNum = 10000;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function setTimeLimit($microsecs)
    {
        set_time_limit($microsecs);
    }

    public function setMaxUrlNumber($num)
    {
        $this->maxCrawlNum = $num;
    }

    public function excludeExtensions($extensions)
    {
        $this->exludeExtensions = $extensions;
    }

    public function excludeFiles($urls)
    {
        $this->excludeFiles = $urls;
    }

    public function excludeDirectories($urls)
    {
        $this->excludeDirs = $urls;
    }

    public function crawl($startUrl)
    {
        $pageCounter = 0;
        $test = [];
        $this->baseUrl = static::formatUrl($startUrl);
        $allUrls = [$this->baseUrl];
        while (count($allUrls) > 0) {
            $url = array_pop($allUrls);
            if (!in_array($url, $this->links)) {
                $pageCounter++;
                $this->links[] = $url;
                $urls = $this->getPageLinks($url);
                $urls = array_values(array_diff($urls, $this->links));
                $test[$url] = array_unique($urls);
                $allUrls = array_values(array_unique(array_merge($allUrls, $urls)));

                if ($pageCounter >= $this->maxCrawlNum) {
                    break;
                }
            }
        }

        $this->links = $this->stripUnqualifiedUrls($this->links);
        sort($this->links, SORT_STRING);

        return $this->links;
    }

    public function getPageLinks($url, $requestMethod = 'GET')
    {
        $isDebug = false;
        $url = $this->formatUrl($url);
        $crawler = $this->client->request($requestMethod, $url);
        if ($crawler) {
            $links = array_unique($crawler->filter('a:not([rel=nofollow])')->extract(['href']));
            if ($isDebug) {
                echo 'URL: '.$url."\n";
                echo 'Preparsing: '.print_r($links, true);
            }
            $links = $this->filterLinks($links, $url);
            $links = array_values(array_unique($links));
            if ($isDebug) {
                echo 'Afterparsing: '.print_r($links, true);
            }
            return $links;
        }
    }

    private function filterLinks($links, $baseUrl)
    {
        $links = array_values($links);
        for ($key = count($links) - 1; $key >= 0; $key--) {
            $link = $links[$key];
            if (!static::isOfSameDomain($link)) {
                unset($links[$key]);
            } else {
                $links[$key] = $this->relativeToAbsoluteUrl($link, $baseUrl);
            }
        }
        return array_values($links);
    }

    private function stripUnqualifiedUrls($links)
    {
        $rootUrl = $this->getRootUrl($this->baseUrl);
        $startDir = substr($this->baseUrl, strlen($rootUrl));

        $isStartsWithSubdir = !empty($startDir) && '/' !== $startDir;
        $isHaveExcludedDirs = count($this->excludeDirs) > 0;
        $isHaveExcludedFiles = count($this->excludeFiles) > 0;
        $isHaveExcludedExtensions = count($this->exludeExtensions) > 0;

        if ($isHaveExcludedFiles | $isHaveExcludedDirs | $isStartsWithSubdir | $isHaveExcludedExtensions) {
            $links = array_values($links);
            for ($key = count($links) - 1; $key >= 0; $key--) {
                $link = $absoluteUrl = $links[$key];
                $relativeUrl = substr($absoluteUrl, strlen($rootUrl));
                $isUnsetUrl = false;
                if ($isStartsWithSubdir) {
                    if (!$this->isWithinUrl($absoluteUrl, $this->baseUrl)) {
                        $isUnsetUrl = true;
                    }
                }
                if (!$isUnsetUrl) {
                    if ($isHaveExcludedFiles && in_array($relativeUrl, $this->excludeFiles)) {
                        $isUnsetUrl = true;
                    }
                }
                if (!$isUnsetUrl) {
                    if ($isHaveExcludedDirs) {
                        foreach ($this->excludeDirs as $excludeDir) {
                            if ($this->isWithinUrl($absoluteUrl, $rootUrl.$excludeDir)) {
                                $isUnsetUrl = true;
                                break;
                            }
                        }
                    }
                }
                if (!$isUnsetUrl) {
                    if ($isHaveExcludedExtensions) {
                        foreach ($this->exludeExtensions as $excludeExtension) {
                            if (strtolower($this->getFileExtension($absoluteUrl)) == strtolower($excludeExtension)) {
                                $isUnsetUrl = true;
                                break;
                            }
                        }
                    }
                }
                if (!$isUnsetUrl) {
                    $links[$key] = $absoluteUrl;
                } else {
                    unset($links[$key]);
                }
            }
        }

        $links = array_values($links);

        return $links;
    }

    public static function relativeToAbsoluteUrl($url, $baseUrl)
    {
        $url = static::formatUrl($url);
        $baseUrl = static::currentDirectory($baseUrl);

        if (empty($url)) {
            return $baseUrl;
        }

        // global path
        if (static::isAbsoluteUrl($url)) {
            return $url;
        }

        // root dir
        if (substr($url, 0, 1) == '/') {
            if (strlen($url) == 1) {
                $url = '';
            }
            $baseUrl = static::getRootUrl($baseUrl);
            return $baseUrl.$url;
        }

        // parent dirs
        $pattern = '../';
        $subDirNum = substr_count($url, $pattern);
        if ($subDirNum > 0) {
            $urlParts = explode('/', $baseUrl);
            $urlPartsNum = count($urlParts);
            if ($subDirNum > $urlPartsNum - 3) {
                $subDirNum = $urlPartsNum - 3;
            }
            array_splice($urlParts, $urlPartsNum - $subDirNum);
            $baseUrl = implode('/', $urlParts);
            $url = '/'.str_replace($pattern, '', $url);
            return $baseUrl.$url;
        }

        return $baseUrl.'/'.$url;
    }

    public static function formatUrl($url)
    {
        $isAbsoluteUrl = static::isAbsoluteUrl($url);
        $url = preg_replace('!(^(https?:)?//|#.*$|^(javascript|mailto|tel):.*)!', '', $url); // strip protocol, hash content
        $url = preg_replace('!/+!', '/', $url); // strip double slashes
        $url = preg_replace('!^(.+)/$!', '\\1', $url); // strip ending slash
        if ($isAbsoluteUrl) {
            $url = '//'.$url;
        }
        return $url;
    }

    public static function isAbsoluteUrl($url)
    {
        return preg_match('!^(https?:)?//!', $url) === 1;
    }

    protected function isOfSameDomain($url)
    {
        $url = static::formatUrl($url);
        if (static::isAbsoluteUrl($url)) {
            $domainRoot = static::getRootUrl($this->baseUrl);
            return $domainRoot === substr($url, 0, strlen($domainRoot));
        }
        return true;
    }

    public static function isWithinUrl($lookupUrl, $baseUrl)
    {
        $baseUrl = static::formatUrl($baseUrl);
        $lookupUrl = static::formatUrl($lookupUrl);
        return $baseUrl === substr($lookupUrl, 0, strlen($baseUrl));
    }

    public static function getFileExtension($url)
    {
        $urlParts = parse_url($url);
        if (isset($urlParts['path'])) {
            return pathinfo($urlParts['path'], PATHINFO_EXTENSION);
        }
        return '';
    }

    public static function currentDirectory($url)
    {
        $url = static::formatUrl($url);
        $urlParts = explode('/', $url);
        if (!(static::isAbsoluteUrl($url) && count($urlParts) == 3)) {
            $lastUrlPartIndex = count($urlParts) - 1;
            $lastUrlPart = $urlParts[$lastUrlPartIndex];
            if (false !== strstr($lastUrlPart, '.')) {
                unset($urlParts[$lastUrlPartIndex]);
                $url = implode('/', $urlParts);
            }
            if (empty($url)) {
                $url = '/';
            }
        }
        return $url;
    }

    public static function getRootUrl($url)
    {
        return '//'.parse_url($url, PHP_URL_HOST);
    }
}
