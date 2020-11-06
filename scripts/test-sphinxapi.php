<?

require_once ( "libs/3rdparty/sphinxapi.php" );

$CONF['sphinx_host'] = "staging.geograph.internal";
$CONF['sphinx_port'] = 9312;
$CONF['sphinx_prefix'] = '';

$offset = 0;
$q = "bridge";
$index = 'gi_stemmed';

                $cl = new SphinxClient ();
                $cl->SetServer ( $CONF['sphinx_host'], $CONF['sphinx_port'] );
                $cl->SetLimits($offset,25);
                $res = $cl->Query ( $q, $CONF['sphinx_prefix'].$index );

                // --------------

                if ( $res===false )
                {
                        if ( $cl->GetLastError() )
                                print "\nError: " . $cl->GetLastError() . "\n\n";
                        print "\tQuery failed: -- please try again later.\n";
                        exit;
                } else
                {
                        if ( $cl->GetLastWarning() )
                                print "\nWARNING: " . $cl->GetLastWarning() . "\n\n";

                        $query_info = "Query '$q' retrieved ".count($res['matches'])." of $res[total_found] matches in $res[time] sec.\n";
                }

		print $query_info;
		print_r($res);
		print "\n\n";