# Sitemaps
Sitemap builder &amp; site crawler  
  
// example  
use Goutte\Client;  
use Cargo\Sitemap\Fetcher;  
use Cargo\Sitemap\Builder;  
  
$client = new Client();  

$fetcher = new Fetcher($client);  
$fetcher->setMaxUrlNumber(100);  
$fetcher->excludeDirectories(['/test-dir']);  
$fetcher->excludeFiles(['/sitemap.html']);  
$urls = $fetcher->crawl('http://www.example.com');  
  
$builder = new Builder();  
echo $builder->output($urls);  
