<?php

/*

  Dynamic DNS / DYNDNS HTACCESS Updater
  ^ 12-2014 - david hunt - holodyn.com

  Read $inputFileName file
  Look for lines following the format
    # DYNDNS {hostname} {notes}
  Pass {hostname} to shell `host {hostname}`
  Overwrite following line with new result

 */

/*
  Options
 */
  $inputFileName        = '.htaccess';
  $outputFileName       = '.htaccess';
  $backupInputFile      = true;
  $onErrorUseOriginal   = true;
  $echoOutput           = false;
  $mindiskspace         = 1073741824;

/*
  Internal Store
 */
  $outputRows           = array();
  $followTask           = null;
  $host_ip              = null;
  $host_dns             = null;
  $host_result          = null;
  $diskspace            = null;

/*
  Check disk space
  */
  $diskspace = disk_free_space("/");
  if( $diskspace <= $mindiskspace){
    exit("Disk space is below minimum threshold ($diskspace)");
  }
/*
  Open, read, parse, write, echo
 */
  if( $fh = fopen($inputFileName, 'r') ){
    while( isset($fh) && !feof($fh) && $line = fgets($fh) ){
      $line = trimLine( $line);
      if( preg_match('/^\# DYNDNS ([0-9A-Za-z\.\-]+)(\s*|\s.*)$/', $line, $matches) ){
        # DYNDNS account.dyndns.org
        $followTask = 'dns_allow';
        $host_ip = null;
        $host_dns = $matches[1];
        $host_result = trimLine( shell_exec('host ' . $host_dns) );
        if( !empty($host_result) && preg_match('/^.* ([\d\.]+)$/', $host_result) ){
          $host_ip = preg_replace('/^.* ([\d\.]+)$/', '$1', $host_result);
        }
        $outputRows[] = $line;
      }
      else if( !empty($followTask) ){
        switch( $followTask ){
          case 'dns_allow':
            if( empty($host_ip) ){
              if( $onErrorUseOriginal ){
                $outputRows[] = $line;
              }
              else {
                $outputRows[] = '# host lookup failed: ' . $host_dns . ' - ' . $host_result;
              }
            }
            else {
              $outputRows[] = 'Allow from ' . $host_ip; // . ' # ' . $host_result;
            }
            break;
          case 'default':
            $outputRows[] = $line;
            break;
        }
        $followTask = null;
      }
      else {
        $outputRows[] = $line;
      }
    }
    fclose( $fh );

    if( $backupInputFile ){
      copy( $inputFileName, $inputFileName.'.bak' );
    }

    if( !empty($outputRows) ){
      $fw = fopen($outputFileName, 'w');
      fwrite( $fw, implode("\n", $outputRows) );
      fclose( $fw );
    }

    if( !empty($echoOutput) ){
      echo implode( "\n", $outputRows );
    }
  }

/*
  Trim CR from tail
 */
  function trimLine( $line ){
    return preg_replace('/[\n\r].*/s','',$line);
  }
