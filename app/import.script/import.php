<?php

include 'import.conf.php';

resetLog();

try {
    $dbc = new PDO("mysql:dbname=$dbname;host=$dbhost", $dbuser, $dbpass);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage() . PHP_EOL);
}

// if table doesn't exist, create one
if ($dbc->query('show tables LIKE \'report\'')->fetch() === false) {
    logMessage('Table report doesn\'t exist... Creating...');
    if (!createTableReport()) {
        die('Couldn\'t create table \'report\'.');        
    }
}

// if table doesn't exist, create one
if ($dbc->query('show tables LIKE \'rptrecord\'')->fetch() === false) {
    logMessage('Table rptrecord doesn\'t exist... Creating...');
    if (!createTableRptrecord()) {
        die('Couldn\'t create table \'rptrecord\'.');        
    }
}

set_time_limit(0); 
/* try to connect */
$hostname = '{' . $host . '}' . $folderInbox;

$inbox = imap_open($hostname,$username,$password) or die('Cannot connect to Email: ' . imap_last_error());

$emails = imap_search($inbox, 'ALL');

/* if any emails found, iterate through each email */
if($emails) {

    $count = 1;

    /* put the newest emails on top */
    rsort($emails);

    /* for every email... */
    foreach($emails as $email_number) 
    {

        /* get information specific to this email */
        $overview = imap_fetch_overview($inbox,$email_number,0);
        logMessage('Processing message...  Subject:"' . $overview[0]->subject . '"');
        //$message = imap_fetchbody($inbox,$email_number,2);

        /* get mail structure */
        $structure = imap_fetchstructure($inbox, $email_number);
        //var_dump($structure);
        $attachments = array();

        $attachments['body'] = array(
            'is_attachment' => false,
            'filename' => '',
            'name' => '',
            'attachment' => ''
        );

        /* if any attachments found... */
        if($structure->ifdparameters) 
        {
            foreach($structure->dparameters as $object) 
            {
                if(strtolower($object->attribute) == 'filename') 
                {
                    $attachments['body']['is_attachment'] = true;
                    $attachments['body']['filename'] = $object->value;
                }
            }
        }

        if($structure->ifparameters) 
        {
            foreach($structure->parameters as $object) 
            {
                if(strtolower($object->attribute) == 'name') 
                {
                    $attachments['body']['is_attachment'] = true;
                    $attachments['body']['name'] = $object->value;
                }
            }
        }

        if($attachments['body']['is_attachment']) 
        {
            $attachments['body']['attachment'] = imap_fetchbody($inbox, $email_number,1);
            
            /* 3 = BASE64 encoding */
            if($structure->encoding == 3) 
            { 
                $attachments['body']['attachment'] = base64_decode($attachments['body']['attachment']);
            }
            /* 4 = QUOTED-PRINTABLE encoding */
            elseif($structure->encoding == 4) 
            { 
                $attachments['body']['attachment'] = quoted_printable_decode($attachments['body']['attachment']);
            }
        }

        if(isset($structure->parts) && count($structure->parts)) 
        {
            for($i = 0; $i < count($structure->parts); $i++) 
            {
                $attachments[$i] = array(
                    'is_attachment' => false,
                    'filename' => '',
                    'name' => '',
                    'attachment' => ''
                );

                if($structure->parts[$i]->ifdparameters) 
                {
                    foreach($structure->parts[$i]->dparameters as $object) 
                    {
                        if(strtolower($object->attribute) == 'filename') 
                        {
                            $attachments[$i]['is_attachment'] = true;
                            $attachments[$i]['filename'] = $object->value;
                        }
                    }
                }

                if($structure->parts[$i]->ifparameters) 
                {
                    foreach($structure->parts[$i]->parameters as $object) 
                    {
                        if(strtolower($object->attribute) == 'name') 
                        {
                            $attachments[$i]['is_attachment'] = true;
                            $attachments[$i]['name'] = $object->value;
                        }
                    }
                }

                if($attachments[$i]['is_attachment']) 
                {
                    $attachments[$i]['attachment'] = imap_fetchbody($inbox, $email_number, $i+1);

                    /* 3 = BASE64 encoding */
                    if($structure->parts[$i]->encoding == 3) 
                    { 
                        $attachments[$i]['attachment'] = base64_decode($attachments[$i]['attachment']);
                    }
                    /* 4 = QUOTED-PRINTABLE encoding */
                    elseif($structure->parts[$i]->encoding == 4) 
                    { 
                        $attachments[$i]['attachment'] = quoted_printable_decode($attachments[$i]['attachment']);
                    }
                }
            }
        }

        /* iterate through each attachment */
        foreach($attachments as $attachment)
        {
            if($attachment['is_attachment'] == 1)
            {
                $filename = $attachment['name'];
                if(empty($filename)) $filename = $attachment['filename'];

                $folder = "attachment";
                if(!is_dir($folder))
                {
                     mkdir($folder);
                }

                logMessage('  Processing attachment: ' . $filename);

                if (preg_match('/.zip$/i',$filename)) {
                    // ******************* ZIP FILE *******************
                    $fp = fopen("./". $folder ."/". $email_number . "-" . $filename, "w+");
                    fwrite($fp, $attachment['attachment']);
                    fclose($fp);
                    
                    $zip = new ZipArchive;
                    $zip->open('./' . $folder . '/' . $email_number . '-' . $filename);
                    $xmlFilename = $zip->getNameIndex(0);
                    $zip->close();
                    $xmlRaw = file_get_contents('zip://' . realpath('./' . $folder . '/' . $email_number . '-' . $filename) . '#' . $xmlFilename);
                    $xml = simplexml_load_string($xmlRaw);
                    unlink('./' . $folder . '/' . $email_number . '-' . $filename);
                } elseif(preg_match('/.gz$/i',$filename)) {
                    // ******************* GZIP FILE *******************
                    $xmlRaw = gzdecode($attachment['attachment']);
                    $xml = simplexml_load_string($xmlRaw);
                } else {
                    logMessage('    Unknown attachment ' . $filename . ' skipping...');
                    continue;
                }
                if ($xml === false) {
                    logMessage('    Xml load failed attachment ' . $filename . ' skipping...');
                    continue;
                }
                $xmlData = readXmlData($xml);
                storeXmlData($xmlData,$xmlRaw);
            }
        }
        if (imap_mail_move($inbox,$email_number,$folderProcessed)) {
            imap_expunge($inbox);
        }
    }
} 

/* close the connection */
imap_close($inbox);

logMessage('done.');

function readXmlData($xml) {
    $xmlData['dateFrom'] = (int)$xml->report_metadata->date_range->begin;
    $xmlData['dateTo'] = (int)$xml->report_metadata->date_range->end;
    $xmlData['organization'] = (string)$xml->report_metadata->org_name;
    $xmlData['reportId'] = (string)$xml->report_metadata->report_id;
    $xmlData['email'] = (string)$xml->report_metadata->email;
    $xmlData['extraContactInfo'] = (string)$xml->report_metadata->extra_contact_info;
    $xmlData['domain'] = (string)$xml->policy_published->domain;
    $xmlData['policy_adkim'] = (string)$xml->policy_published->adkim;
    $xmlData['policy_aspf'] = (string)$xml->policy_published->aspf;
    $xmlData['policy_p'] = (string)$xml->policy_published->p;
    $xmlData['policy_sp'] = (string)$xml->policy_published->sp;
    $xmlData['policy_pct'] = (string)$xml->policy_published->pct;

    $recordRow = 0;
    foreach ($xml->record as $record) {
        $xmlData['record'][$recordRow]['ip'] = (string)$record->row->source_ip;
        $xmlData['record'][$recordRow]['count'] = (int)$record->row->count;
        $xmlData['record'][$recordRow]['disposition'] = (string)$record->row->policy_evaluated->disposition;
        $xmlData['record'][$recordRow]['dkim_align'] = (string)$record->row->policy_evaluated->dkim;
        $xmlData['record'][$recordRow]['spf_align'] = (string)$record->row->policy_evaluated->spf;
        $xmlData['record'][$recordRow]['hfrom'] = (string)$record->identifiers->header_from;
        $dkimDomain = $dkimResult = $dkimSelector = '';
        foreach ($record->auth_results->dkim as $dkim) {
            $dkimDomain .= (string)$dkim->domain . '/';
            $dkimResult .= (string)$dkim->result . '/';
            $dkimSelector .= (string)$dkim->selector . '/';
        }
        $xmlData['record'][$recordRow]['dkimDomain'] = trim($dkimDomain,'/');
        $xmlData['record'][$recordRow]['dkimResult'] = trim($dkimResult,'/');
        $xmlData['record'][$recordRow]['dkimSelector'] = trim($dkimSelector,'/');
        $xmlData['record'][$recordRow]['spfDomain'] = (string)$record->auth_results->spf->domain;
        $xmlData['record'][$recordRow]['spfResult'] = (string)$record->auth_results->spf->result;
        $recordRow++;
    }
    return $xmlData;
}

function storeXmlData($xmlData,$xmlRaw) {
    global $dbc;
    // check if report already exists
    $queryCheck = $dbc->prepare('SELECT serial AS count FROM report WHERE reportid=? AND domain=?');
    $parametersCheck[] = $xmlData['reportId'];
    $parametersCheck[] = $xmlData['domain'];
    $queryCheck->execute($parametersCheck);
    if ($queryCheck->fetch()) {
        logMessage('    Report already exists. reportId: ' . $xmlData['reportId'] . ' domain: ' . $xmlData['domain'] . ' skipping...');
        return;
    }

    // insert report
    $queryReport = $dbc->prepare('INSERT INTO report(serial,mindate,maxdate,domain,org,reportid,email,extra_contact_info,policy_adkim, policy_aspf, policy_p, policy_sp, policy_pct, raw_xml)
        VALUES(NULL,FROM_UNIXTIME(?),FROM_UNIXTIME(?),?,?,?,?,?,?,?,?,?,?,?)');
    $parametersReport[] = $xmlData['dateFrom'];
    $parametersReport[] = (strlen($xmlData['dateTo']) > 0) ? $xmlData['dateTo'] : NULL;
    $parametersReport[] = $xmlData['domain'];
    $parametersReport[] = $xmlData['organization'];
    $parametersReport[] = $xmlData['reportId'];
    $parametersReport[] = (strlen($xmlData['email']) > 0) ? $xmlData['email'] : NULL;
    $parametersReport[] = (strlen($xmlData['extraContactInfo']) > 0) ? $xmlData['extraContactInfo'] : NULL;;
    $parametersReport[] = (strlen($xmlData['policy_adkim']) > 0) ? $xmlData['policy_adkim'] : NULL;
    $parametersReport[] = (strlen($xmlData['policy_aspf']) > 0) ? $xmlData['policy_aspf'] : NULL;
    $parametersReport[] = (strlen($xmlData['policy_p']) > 0) ? $xmlData['policy_p'] : NULL;
    $parametersReport[] = (strlen($xmlData['policy_sp']) > 0) ? $xmlData['policy_sp'] : NULL;
    $parametersReport[] = (strlen($xmlData['policy_pct']) > 0) ? $xmlData['policy_pct'] : NULL;
    $parametersReport[] = base64_encode(gzencode($xmlRaw));
    if (!$queryReport->execute($parametersReport)) {
        logMessage('INSERT INTO report failed. VALUES: ' . json_encode($parametersReport));
        return;
    }
    $serial = $dbc->lastInsertId();

    //insert rptrecord
    foreach ($xmlData['record'] as $record) {
        if (filter_var($record['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $record['ip'] = ip2long($record['ip']);
            $iptype = 'ip';
        } elseif (filter_var($record['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $record['ip'] = inet_pton($record['ip']);
            $iptype = 'ip6';
        } else {
            logMessage('Invalid IP address: ' . $record['ip']);
            continue;
        }
        $queryReportRecord = $dbc->prepare('INSERT INTO rptrecord(serial,' . $iptype . ',rcount,disposition,spf_align,dkim_align,dkimdomain,dkimresult,spfdomain,spfresult,identifier_hfrom)
            VALUES(?,?,?,?,?,?,?,?,?,?,?)');
        $parametersReportRecord = NULL;
        $parametersReportRecord[] = $serial;
        $parametersReportRecord[] = $record['ip'];
        $parametersReportRecord[] = $record['count'];
        // some reports contain disposition pass instead of none
        if ($record['disposition'] == 'pass') {
            $record['disposition'] = 'none';
        }
        $parametersReportRecord[] = $record['disposition'];
        // some reports contain different spf_align instead of fail
        if ($record['spf_align'] == 'none' || $record['spf_align'] == 'softfail' || $record['spf_align'] == 'err' || $record['spf_align'] == 'neutral') {
            $record['spf_align'] = 'fail';
        }
        $parametersReportRecord[] = $record['spf_align'];
        $parametersReportRecord[] = $record['dkim_align'];
        $parametersReportRecord[] = $record['dkimDomain'];
        $parametersReportRecord[] = $record['dkimResult'];
        $parametersReportRecord[] = $record['spfDomain'];
        $parametersReportRecord[] = $record['spfResult'];
        $parametersReportRecord[] = $record['hfrom'];
        if (!$queryReportRecord->execute($parametersReportRecord)) {
            logMessage('INSERT INTO rptrecord failed. VALUES: ' . json_encode($parametersReportRecord));
        }
    }
}

function createTableReport() {
    global $dbc;
    $result = $dbc->query('CREATE TABLE `report` (
        `serial` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `mindate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        `maxdate` timestamp NULL DEFAULT NULL,
        `domain` varchar(255) NOT NULL,
        `org` varchar(255) NOT NULL,
        `reportid` varchar(255) NOT NULL,
        `email` varchar(255) DEFAULT NULL,
        `extra_contact_info` varchar(255) DEFAULT NULL,
        `policy_adkim` varchar(20) DEFAULT NULL,
        `policy_aspf` varchar(20) DEFAULT NULL,
        `policy_p` varchar(20) DEFAULT NULL,
        `policy_sp` varchar(20) DEFAULT NULL,
        `policy_pct` tinyint(3) unsigned DEFAULT NULL,
        `raw_xml` mediumtext DEFAULT NULL,
        PRIMARY KEY (`serial`),
        UNIQUE KEY `domain` (`domain`,`reportid`),
        KEY `maxdate` (`maxdate`)
        ) AUTO_INCREMENT=1');
    if ($result){
        return true;
    } else {
        return false;
    }
}

function createTableRptrecord() {
    global $dbc;
    $result = $dbc->query("CREATE TABLE `rptrecord` (
        `serial` int(10) unsigned NOT NULL,
        `ip` int(10) unsigned DEFAULT NULL,
        `ip6` binary(16) DEFAULT NULL,
        `rcount` int(10) unsigned NOT NULL,
        `disposition` enum('none','quarantine','reject') DEFAULT NULL,
        `reason` varchar(255) DEFAULT NULL,
        `dkimdomain` varchar(255) DEFAULT NULL,
        `dkimresult` varchar(64) DEFAULT NULL,
        `spfdomain` varchar(255) DEFAULT NULL,
        `spfresult` enum('none','neutral','pass','fail','softfail','temperror','permerror','unknown') DEFAULT NULL,
        `spf_align` enum('fail','pass','unknown') NOT NULL,
        `dkim_align` enum('fail','pass','unknown') NOT NULL,
        `identifier_hfrom` varchar(255) DEFAULT NULL,
        `selector` varchar(128) DEFAULT NULL,
        KEY `serial` (`serial`,`ip`),
        KEY `serial6` (`serial`,`ip6`),
        KEY `hfrom-spf-dkim` (`identifier_hfrom`,`spf_align`,`dkim_align`)
        )");
    if ($result){
        return true;
    } else {
        return false;
    }
}

function logMessage($message) {
    echo($message . PHP_EOL);
    file_put_contents('./import.log',$message . PHP_EOL,FILE_APPEND);
}

function resetLog() {
    file_put_contents('./import.log','');
}

?>