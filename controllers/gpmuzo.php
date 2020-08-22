<?php

/**
 * @category    Payment
 * @author      Global Payments Europe s.r.o. (emailgpwebpay@gpe.cz)
 */

function GpMuzo_CreateOrder(// funkce presmeruje browser s pozadavkem na server Muzo     
$urlMuzoCreateOrder, // adresa kam posilat pozadavek do Muzo
        $replyUrl, // adresa kam ma Muzo presmerovat odpoved
        $privateKeyFile, // soubor s privatnim klicem
        $privateKeyPass, // heslo privatniho klice
        $merchantNumber, // cislo obchodnika
        $orderNumber, // cislo objednavky
        $amount, // hodnota objednavky v halerich
        $currency, // kod meny, CZK..203, EUR..978, GBP..826, USD..840, povolene meny zalezi na smlouve s bankou
        $depositFlag, // uhrada okamzite "1", nebo uhrada az z admin rozhrani
        $merOrderNum, // identifikace objednavky pro obchodnika
        $description, // popis nakupu, pouze ASCII
        $md, // data obchodnika, pouze ASCII
        $lang = null, // jazyk na brane dle ISO 639-1 (cs, en, de, sk, ...), pokud null, zobrazi se jazyk dle jazykoveho nastaveni prohlizece
        $email = null) {            // E-mail držitele karty, použije se pro notifikaci výsledku platby a v antifraud systémech
    $getfs = GpMuzo_CreateOrderExt($urlMuzoCreateOrder, $replyUrl, $privateKeyFile, $privateKeyPass, $merchantNumber, $orderNumber, $amount, $currency, $depositFlag, $merOrderNum, $description, $md, $lang, $email);
    Header("Location: $getfs");
    return $getfs; // vracene url muze byt pouzito napriklad pro logovani
}

function GpMuzo_ReceiveReply(// funkce zpracuje a overi zpetne presmerovani z Muzo
$muzoPublicKeyFile, // soubor s verejnym klicem Muzo
        &$orderNumber, // cislo objednavky
        &$merOrderNum, // identifikace objednavky pro obchodnika
        &$md, // data obchodnika, pouze ASCII
        &$prCode, // primarni kod
        &$srCode, // sekundarni kod
        &$resultText, // slovni popis chyby
        $merchantNumber = null) { // cislo obchodnika, pro komaptibilitu s puvodni verzi brany muze byt null, ale to je na ukor bezpecnosti
    // Pozor! platba probehla uspesne pouze pokud funkce vrati true a zaroven je $prCode i $srCode rovne 0
    parse_str($_SERVER['QUERY_STRING'], $getvars); // parsujeme vlastnim zpusobem protoze trida JURI aktivovana z povolenehi JoomSEFu provede dvojnasobne URL decode GET promenych
    if (!isset($getvars["ORDERNUMBER"])) {          // ale kdyz tam neni ocekavana polozka, tak to asi zkazil nejaky plugin v opencartu, takze pouzijeme klasiku
        $getvars = $_REQUEST;
    }

    $signHash = "CREATE_ORDER";
    $orderNumber = $getvars["ORDERNUMBER"];
    $signHash .= "|" . $orderNumber;
    $merOrderNum = $getvars["MERORDERNUM"];
    $signHash .= "|" . $merOrderNum;
    $md = $getvars["MD"];
    if ($md != '')
        $signHash .= "|" . $md;
    $prCode = $getvars["PRCODE"];
    $signHash .= "|" . $prCode;
    $srCode = $getvars["SRCODE"];
    $signHash .= "|" . $srCode;
    if (isset($getvars["RESULTTEXT"])) {
        $resultText = $getvars["RESULTTEXT"];
        $signHash .= "|" . $resultText;
    } else {
        $resultText = '';
    }

    $digest = $getvars["DIGEST"];
    $digest1 = $getvars["DIGEST1"];
    if (strpos($digest, ' ') !== false || (strpos($digest1, ' ') !== false)) {
        $digest = str_replace(' ', '+', $digest);
        $digest1 = str_replace(' ', '+', $digest1);
    }
    $digok = GpMuzo_Verify($signHash, $digest, $muzoPublicKeyFile);
    if (!is_null($merchantNumber)) {
        $digok = $digok && GpMuzo_Verify($signHash . '|' . $merchantNumber, $digest1, $muzoPublicKeyFile);
    }
    return $digok;  // urcuje zda byl podpis verohodny, stav provedeni platby je vsak urcen vracenym argumenten prCode!
}

// vnitrni funkce
function GpMuzo_CreateOrderExt(// pomocna, funkce pripravi url pro presmerovani na Muzo
$urlMuzoCreateOrder, // adresa kam posilat pozadavek do Muzo
        $replyUrl, // adresa kam ma Muzo presmerovat odpoved
        $privateKeyFile, // soubor s privatnim klicem
        $privateKeyPass, // heslo privatniho klice
        $merchantNumber, // cislo obchodnika
        $orderNumber, // cislo objednavky
        $amount, // hodnota objednavky v halerich
        $currency, // kod meny, CZK..203, EUR..978, GBP..826, USD..840, povolene meny zalezi na smlouve s bankou
        $depositFlag, // uhrada okamzite "1", nebo uhrada az z admin rozhrani
        $merOrderNum, // identifikace objednavky pro obchodnika
        $description, // popis nakupu, pouze ASCII
        $md, // data obchodnika, pouze ASCII
        $lang = null, // jazyk na brane dle ISO 639-1 (cs, en, de, sk, ...), pokud null, zobrazi se jazyk dle jazykoveho nastaveni prohlizece
        $email = null) {            // E-mail držitele karty, použije se pro notifikaci výsledku platby a v antifraud systémech
    // nasledujici data musi byt bez mezer na konci, jinak selze podpis. V dokumentaci neuvedeno.
    $description = trim($description);
    $md = trim($md);

    $operation = "CREATE_ORDER";
    $digest = GpMuzo_Digest($privateKeyFile, $privateKeyPass, $replyUrl, $operation, $merchantNumber, $orderNumber, $amount, $currency, $depositFlag, $merOrderNum, $description, $md, $email);

    $getfs = $urlMuzoCreateOrder . "?";
    $getfs .= "MERCHANTNUMBER=" . urlencode($merchantNumber) . "&";
    $getfs .= "OPERATION=" . urlencode($operation) . "&";
    $getfs .= "ORDERNUMBER=" . urlencode($orderNumber) . "&";
    $getfs .= "AMOUNT=" . urlencode($amount) . "&";
    $getfs .= "CURRENCY=" . urlencode($currency) . "&";
    $getfs .= "DEPOSITFLAG=" . urlencode($depositFlag) . "&";
    $getfs .= "MERORDERNUM=" . urlencode($merOrderNum) . "&";
    $getfs .= "URL=" . urlencode($replyUrl) . "&";
    $getfs .= "DESCRIPTION=" . urlencode($description) . "&";
    $getfs .= "MD=" . urlencode($md) . "&";
    $getfs .= "DIGEST=" . urlencode($digest);
    if ($lang !== null) {
        $getfs .= "&LANG=" . urlencode($lang);
    }
    if ($email !== null) {
        $getfs .= "&EMAIL=" . urlencode($email);
    }

    return $getfs; // vracene url pro presmerovani na gpwebpay
}

function GpMuzo_Digest(// funkce vrati podepsany digest pozadavku
$privateKeyFile, // soubor s privatnim klicem
        $privateKeyPass, // heslo privatniho klice
        $replyUrl, // adresa kam ma Muzo presmerovat odpoved
        $operation, // pouze CREATE_ORDER
        $merchantNumber, // cislo obchodnika
        $orderNumber, // cislo objednavky
        $amount, // hodnota objednavky v halerich
        $currency, // kod meny (pro ceske PayMuzo funguje pouze kod 203, coz je CZK)
        $depositFlag, // uhrada okamzite "1", nebo uhrada az z admin rozhrani
        $merOrderNum, // identifikace objednavky pro obchodnika
        $description, // popis nakupu, pouze ASCII
        $md, // data obchodnika, pouze ASCII
        $email) {                 // email zakaznika
    $digestSrc = $merchantNumber . "|" . $operation . "|" . $orderNumber . "|" . $amount . "|" . $currency . "|" . $depositFlag . "|" . $merOrderNum . "|" . $replyUrl . "|" . $description . "|" . $md;
    if ($email !== null) {
        $digestSrc .= "|" . $email;
    }
    if ($digestSrc[strlen($digestSrc) - 1] == '|')
        $digestSrc = substr($digestSrc, 0, strlen($digestSrc) - 1);   // korekce chyby v implementaci GP
    $digest = GpMuzo_Sign($digestSrc, $privateKeyFile, $privateKeyPass);

    return $digest;
}

function GpMuzo_Sign($text, $keyFile, $password) {
    $fp = fopen($keyFile, "r");
    $privatni = fread($fp, filesize($keyFile));
    fclose($fp);
    $pkeyid = openssl_get_privatekey($privatni, $password);
    openssl_sign($text, $signature, $pkeyid);
    $signature = base64_encode($signature);
    openssl_free_key($pkeyid);
    return $signature;
}

function GpMuzo_Verify($text, $sigb64, $keyFile) {
    $fp = fopen($keyFile, "r");
    $verejny = fread($fp, filesize($keyFile));
    fclose($fp);
    $pubkeyid = openssl_get_publickey($verejny);
    $signature = base64_decode($sigb64);
    $vysledek = openssl_verify($text, $signature, $pubkeyid);
    openssl_free_key($pubkeyid);
    return (($vysledek == 1) ? true : false);
}
