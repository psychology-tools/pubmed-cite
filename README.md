# psychology-tools/pubmed-cite

A library for formatting and fetching citations to pubmed articles from the eFetch API.

## Examples

### Batch Requests
If you have to perform several lookups, you should queue and batch request them for performance reasons.

```
require_once('vendor/autoload.php');

use PsychologyTools\PubmedCite\PubmedCite;

$pubmed = new PubmedCite();

$p->queue('11111'); // queue single pmid as string
$p->queue(12345);   // or as an int
$p->queue( ['43234',42234] ); // or either as an array
$p->queue( '344334,22424,444444'); // or as a comma separated string
$p->queue( '424245 X 455534 X 4534343' ); // or any kind of separated string

$citations = $pubmed->fetch_queued();  // returns an array of citation data objects indexed by pmid
var_dump($citations);
```
