<?php

if ($autoloaderPath = realpath(getcwd() . '/vendor/autoload.php')) {
    require $autoloaderPath;
}

$client = new Goutte\Client();
$fetcher = new Cargo\Sitemap\Fetcher($client);
$fetcher->setTimeLimit(1800);
$fetcher->setMaxUrlNumber(86);
$fetcher->excludeDirectories(['/build-and-equip/option-details']);
//$fetcher->excludeFiles([]);
$urls = $fetcher->crawl('http://www.thecargoagency.com/');

$builder = new Cargo\Sitemap\Builder();
echo $builder->build($urls);
die;

//print_r(SitemapGenerator::getPageLinks());

/*
// simple script to crawl links

function crawl_page($url, $depth = 5)
{
    static $seen = array();
    if (isset($seen[$url]) || $depth === 0) {
        return;
    }

    $seen[$url] = true;

    $dom = new DOMDocument('1.0');
    @$dom->loadHTMLFile($url);

    $anchors = $dom->getElementsByTagName('a');
    foreach ($anchors as $element) {
        $href = $element->getAttribute('href');
        if (0 !== strpos($href, 'http')) {
            $path = '/' . ltrim($href, '/');
            if (extension_loaded('http')) {
                $href = http_build_url($url, array('path' => $path));
            } else {
                $parts = parse_url($url);
                $href = $parts['scheme'] . '://';
                if (isset($parts['user']) && isset($parts['pass'])) {
                    $href .= $parts['user'] . ':' . $parts['pass'] . '@';
                }
                $href .= $parts['host'];
                if (isset($parts['port'])) {
                    $href .= ':' . $parts['port'];
                }
                $href .= $path;
            }
        }
        crawl_page($href, $depth - 1);
    }
    echo "URL:",$url,PHP_EOL,"CONTENT:",PHP_EOL,$dom->saveHTML(),PHP_EOL,PHP_EOL;
}

crawl_page("http://hobodave.com", 2);
*/