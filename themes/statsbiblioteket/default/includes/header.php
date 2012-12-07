<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<?php



/**
 * Support the htmlinject hook, which allows modules to change header, pre and post body on all pages.
 */
$this->data['htmlinject'] = array(
    'htmlContentPre' => array(),
    'htmlContentPost' => array(),
    'htmlContentHead' => array(),
);


$jquery = array();
if (array_key_exists('jquery', $this->data)) $jquery = $this->data['jquery'];

if (array_key_exists('pageid', $this->data)) {
    $hookinfo = array(
        'pre' => &$this->data['htmlinject']['htmlContentPre'],
        'post' => &$this->data['htmlinject']['htmlContentPost'],
        'head' => &$this->data['htmlinject']['htmlContentHead'],
        'jquery' => &$jquery,
        'page' => $this->data['pageid']
    );

    SimpleSAML_Module::callHooks('htmlinject', $hookinfo);
}
// - o - o - o - o - o - o - o - o - o - o - o - o -




?>
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <script type="text/javascript" src="/<?php echo $this->data['baseurlpath']; ?>resources/script.js"></script>
    <title id="chooseHeadingTitleText">Log på</title>
    <script type="text/javascript" src="/<?php echo $this->data['baseurlpath']; ?>resources/jquery-16.js"></script>
    <!-- <script type="text/javascript">
        $(document).ready(function() {
            $(".flip").click(function() {
                $(".panel").slideToggle("slow");
                if ($(".flip").html() == "<?php echo $this->t('{sbcasserver:discopage:moredescription_heading_show_less}')?>") {
                    $(".flip").html("<?php echo $this->t('{sbcasserver:discopage:moredescription_heading_show_more}')?>");
                } else {
                    $(".flip").html("<?php echo $this->t('{sbcasserver:discopage:moredescription_heading_show_less}')?>");
                }
            });
        });
    </script>
    -->

    <link rel="stylesheet" type="text/css"
          href="/<?php echo $this->data['baseurlpath']; ?>module.php/sbcasserver/resources/default.css"/>
    <link rel="icon" type="image/icon" href="/<?php echo $this->data['baseurlpath']; ?>resources/icons/favicon.ico"/>

    <?php

    if (!empty($jquery)) {
        $version = '1.5';
        if (array_key_exists('version', $jquery))
            $version = $jquery['version'];

        if ($version == '1.5') {
            if (isset($jquery['core']) && $jquery['core'])
                echo('<script type="text/javascript" src="/' . $this->data['baseurlpath'] . 'resources/jquery.js"></script>' . "\n");

            if (isset($jquery['ui']) && $jquery['ui'])
                echo('<script type="text/javascript" src="/' . $this->data['baseurlpath'] . 'resources/jquery-ui.js"></script>' . "\n");

            if (isset($jquery['css']) && $jquery['css'])
                echo('<link rel="stylesheet" media="screen" type="text/css" href="/' . $this->data['baseurlpath'] .
                    'resources/uitheme/jquery-ui-themeroller.css" />' . "\n");

        } else if ($version == '1.6') {
            if (isset($jquery['core']) && $jquery['core'])
                echo('<script type="text/javascript" src="/' . $this->data['baseurlpath'] . 'resources/jquery-16.js"></script>' . "\n");

            if (isset($jquery['ui']) && $jquery['ui'])
                echo('<script type="text/javascript" src="/' . $this->data['baseurlpath'] . 'resources/jquery-ui-16.js"></script>' . "\n");

            if (isset($jquery['css']) && $jquery['css'])
                echo('<link rel="stylesheet" media="screen" type="text/css" href="/' . $this->data['baseurlpath'] .
                    'resources/uitheme16/ui.all.css" />' . "\n");
        }
    }

    if (!empty($this->data['htmlinject']['htmlContentHead'])) {
        foreach ($this->data['htmlinject']['htmlContentHead'] AS $c) {
            echo $c;
        }
    }




    ?>


    <meta name="robots" content="noindex, nofollow"/>


    <?php
    if (array_key_exists('head', $this->data)) {
        echo '<!-- head -->' . $this->data['head'] . '<!-- /head -->';
    }
    ?>
</head>
<?php
$onLoad = '';
if (array_key_exists('autofocus', $this->data)) {
    $onLoad .= 'SimpleSAML_focus(\'' . $this->data['autofocus'] . '\');';
}
if (isset($this->data['onLoad'])) {
    $onLoad .= $this->data['onLoad'];
}

if ($onLoad !== '') {
    $onLoad = ' onload="' . $onLoad . '"';
}
?>
<body<?php echo $onLoad; ?>>

<div id="wrap">

    <div id="header">
        <h1><img src="/<?php echo $this->data['baseurlpath']; ?>module.php/sbcasserver/resources/logo1.gif"/></h1>
    </div>

    <!----------------- TRANSLATED TEXTS GO HERE ----------------->
    <script type="text/javascript">
        function goEnglish() {
            $("#chooseHeadingText").html('Log in');
            $("#descriptionHeadingText").html('Help?');
            $("#descriptionText").html(
                'You are about to log in to a service at the State and University Library.<br/>'
                    + '<br/><ul>'
                    + '<li> <b>Databases, e-journals and e-books:</b><br/>'
                    + 'Students and employees at Aarhus University and Aarhus University Hospital have access'
                    + ' to databases, e-journals and e-books. If you are an employee at Aarhus University'
                    + ' Hospital please use your <u>State and University Library</u> login. If you are a student or'
                    + ' employee at Aarhus University you can choose to use your <u>Aarhus University</u> login or'
                    + ' your <u>Aarhus School of Business</u> login instead.</li><br/>'
                    + '<li> <b>User Registration:</b><br/>'
                    + ' Choose <u>Aarhus University</u> if you are a students or staff at the University of Aarhus.<br/>'
                    + ' Choose <u>NemID</u> if you are a private loaner or affiliated with other organizations.</li><br/>'
                    + '<li> <b>Mediestream Portal:</b><br/>'
                    + 'There is access to the Mediestream Portal for students and staff at a number of Danish'
                    + ' educational institutions. Log in is handled through <u>WAYF</u>.</li>'
                    + '</ul>');
            document.title = "Log in";

            /* Get number of idps */
            var numberOfIdpsElement = document.getElementById('numberOfIdps');
            var numberOfIdpsText = numberOfIdpsElement.innerHTML;
            numberOfIdpsText = numberOfIdpsText.substring(4, numberOfIdpsText.length - 3);
            var numberOfIdps = parseInt(numberOfIdpsText);

            var i = 0;
            for (i = 0; i < numberOfIdps; i++) {
                /* Insert English idp header */
                var idpHeaderElement = document.getElementById('englishIdpHeader' + parseInt(i));
                var idpHeaderText = idpHeaderElement.innerHTML;
                document.forms[0].elements[parseInt(i) + 3].value = idpHeaderText;

                /* Insert English idp description */
                var idpDescriptionElement = document.getElementById('englishIdpDescription' + parseInt(i));
                var idpDescriptionText = idpDescriptionElement.innerHTML;
                var idpDescriptionTargetElement = document.getElementById('idpDescription' + parseInt(i));
                idpDescriptionTargetElement.innerHTML = idpDescriptionText;
            }
        }

        function goDanish() {
            $("#chooseHeadingText").html('Log på');
            $("#descriptionHeadingText").html('Hjælp?');
            $("#descriptionText").html(
                'Du er ved at logge på en af Statsbibliotekets tjenester:<br/>'
                    + '<br/><ul>'
                    + '<li> <b>Databaser, e-tidsskrifter og e-bøger:</b><br/>'
                    + 'Der er adgang til databaser, e-tidsskrifter og e-bøger for studerende og ansatte'
                    + ' ved Aarhus Universitet og Aarhus Universitetshospital. Ansatte ved Aarhus'
                    + ' Universitetshospital skal bruge log ind fra <u>Statsbiblioteket</u>. Ansatte og'
                    + ' studerende ved Aarhus Universitet kan i stedet vælge log ind via <u>Aarhus Universitet</u>'
                    + ' eller <u>Handelshøjskolen</u>.</li><br/>'
                    + '<li> <b>Brugeroprettelse:</b><br/>'
                    + 'Vælg <u>Aarhus Universitet</u>, hvis du er studerende eller ansat ved Aarhus Universitet.<br/>'
                    + 'Vælg <u>NemID</u>, hvis du er privat bruger eller tilknyttet andre organisationer.</li><br/>'
                    + '<li> <b>Mediestream:</b><br/>'
                    + 'Der er adgang til Mediestream for studerende og ansatte ved en række danske'
                    + ' uddannelsesinstitutioner. Login håndteres via den nationale login service <u>WAYF</u>.</li>'
                    + '</ul>');
            document.title = "Log på";

            /* Get number of idps */
            var numberOfIdpsElement = document.getElementById('numberOfIdps');
            var numberOfIdpsText = numberOfIdpsElement.innerHTML;
            numberOfIdpsText = numberOfIdpsText.substring(4, numberOfIdpsText.length - 3);
            var numberOfIdps = parseInt(numberOfIdpsText);

            var i = 0;
            for (i = 0; i < numberOfIdps; i++) {
                /* Insert Danish idp header */
                var idpHeaderElement = document.getElementById('danishIdpHeader' + parseInt(i));
                var idpHeaderText = idpHeaderElement.innerHTML;

                document.forms[0].elements[parseInt(i) + 3].value = idpHeaderText;

                /* Insert Danish idp description */
                var idpDescriptionElement = document.getElementById('danishIdpDescription' + parseInt(i));
                var idpDescriptionText = idpDescriptionElement.innerHTML;

                var idpDescriptionTargetElement = document.getElementById('idpDescription' + parseInt(i));
                idpDescriptionTargetElement.innerHTML = idpDescriptionText;
            }

        }
    </script>

    <div style="text-align:right"><a href="" onclick="goDanish(); return false;">Dansk</a> / <a href=""
                                                                                                onClick="goEnglish();
return false;">English</a> &nbsp; &nbsp; </div>
    <?php

    $includeLanguageBar = FALSE;
    if (!empty($_POST))
        $includeLanguageBar = FALSE;
    if (isset($this->data['hideLanguageBar']) && $this->data['hideLanguageBar'] === TRUE)
        $includeLanguageBar = FALSE;

    if ($includeLanguageBar) {


        echo '<div id="languagebar">';
        $languages = $this->getLanguageList();
        $langnames = array(
            'da' => 'Dansk',
            'en' => 'English',
        );

        $textarray = array();
        foreach ($languages AS $lang => $current) {
            if (array_key_exists($lang, $langnames)) {
                if ($current) {
                    $textarray[] = $langnames[$lang];
                } else {
                    SimpleSAML_Logger::debug('PDJ: selfURL: ' . SimpleSAML_Utilities::selfURL());
                    SimpleSAML_Logger::debug('PDJ: add a parameter: ' . SimpleSAML_Utilities::addURLparameter(SimpleSAML_Utilities::selfURL(), array('language' => $lang)));
                    $textarray[] = '<a href="' . htmlspecialchars(SimpleSAML_Utilities::addURLparameter(SimpleSAML_Utilities::selfURL(), array('language' => $lang))) . '">' .
                        $langnames[$lang] . '</a>';
                }
            }
        }
        echo join(' | ', $textarray);
        echo '</div>';

    }



    ?>
    <div id="content">



<?php

if (!empty($this->data['htmlinject']['htmlContentPre'])) {
    foreach ($this->data['htmlinject']['htmlContentPre'] AS $c) {
        echo $c;
    }
}
