<?php

namespace PsychologyTools\PubmedCite;

require_once( __DIR__ . '/../../../autoload.php' );

use Symfony\Component\HttpClient\HttpClient;

class PubmedCite
{
    /** @var array Holds the queued pmids */
    private $pmids = [];
    /** @var array Holds the retreived citation data indexed by pmid */
    private $data = [];
    /** @var array Holds the retreived citation data indexed by optional identifier */
    private $data_by_id = [];
    /** @var array Map of optional identifier to associate with queued pmids */
    private $id_map = [];

    public function __construct()
    {
        /* Initialize empty pmid and data arrays for batch (post) api requests */
    }

    /**
     * Normalizes one or more pmids passed in any format to an array of strings.
     *
     * @param mixed $pmids One or more pmids as an array, character separated string, or integer
     * @return array[string]
     */
    protected function normalize_pmids( $pmids )
    {
        if( is_object($pmids) ) { (array) $pmids; }                  // cast objects to an array
        if( is_array($pmids) ) { $pmids = implode(',', $pmids); }    // implode arrays to comma seperated string
        $pmids = preg_replace( '~[^0-9]+~', ',', (string) $pmids );  // replace anything between contiguous numbers
        $pmids = trim( $pmids, ',' );                                //     with a single comma and trim the outsides
        return explode( ',', $pmids );                               // explode back to array
    }

    /**
     * Adds PMIDs to the queue (with optional mapping) to be retreived with batch().
     *
     * @param string $identifier (optional) Custom identifier to use as an index
     * @param mixed $pmids One or more PMIDs in any format
     * @return int Returns the number of separate PMIDs queued based on input
     */
    public function queue( $pmids, $id=null )
    {
        $pmids = $this->normalize_pmids($pmids);

        /* Merge normalized PMIDs in with existing array */
        $this->pmids = array_merge($this->pmids, $pmids);

        /* Map PMID to ID if one is provided */
        if( !empty($id) ) {
            foreach($pmids as $pmid) {
                $this->id_map[$pmid] = $id;
            }
        }

        return (int) count($pmids);
    }

    /**
     * Fetches all queued PMIDs in a single request.
     *
     * @return array Returns an array of citation objects
     */
    public function fetch_queued()
    {
        if( empty($this->pmids) ) {
            throw new Exception('No PMIDs added to queue, nothing to fetch.');
        }

        try {
            $url = 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?' . http_build_query([
                 'db' => 'pubmed',
                 'rettype' => 'medline',
                 'retmode' => 'xml'
            ], null, '&', PHP_QUERY_RFC3986);

            $client = HttpClient::create();
            $response = $client->request( 'POST', $url, [
                'body' => [
                    'id' => implode(',', $this->pmids)
                ]
            ]);

            /* convert from XML to PHP array of simple objects
             *
             * Note: JSON encoding a SimpleXML resource and JSON decoding the result provides a normal PHP object
             * that is, at least in my opinion, much more intuitive to work with.
             */
            $raw = json_decode( json_encode( simplexml_load_string( $response->getContent() ) ) )->PubmedArticle;

            /* index data array by pmid */
            foreach( $raw as $single) {
                $pmid = $single->MedlineCitation->PMID;
                $this->data[$pmid] = $single;

                /* create pointers with any identifiers */
                if( array_key_exists($pmid, $this->id_map) ) {
                    $id = $this->id_map[$pmid];
                    $this->data_by_id[$id] = @$this->data[$pmid];
                }
            }
            return $this->data;
        }

        catch(Exception $e) { throw $e; }
    }
}


// @TODO https://www.ncbi.nlm.nih.gov/pmc/utils/idconv/v1.0/?tool=my_tool&email=my_email@example.com&ids=PMC3531190&rettype=json
