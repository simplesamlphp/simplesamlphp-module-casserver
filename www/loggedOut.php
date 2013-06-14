<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr">
<head>
    <title><?php echo $this->t('{logout:title}'); ?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
</head>

<body class="logout">

<div id="logout">
<p>
    <?php echo $this->t('{logout:logged_out_text}'); ?>
</p>
</div>

<?php

$includeLanguageBar = TRUE;
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
                $textarray[] = '<a href="' . htmlspecialchars(
                    SimpleSAML_Utilities::addURLparameter(
                        SimpleSAML_Utilities::selfURL(), array('language' => $lang)
                    )
                ) . '">' . $langnames[$lang] . '</a>';
            }
        }
    }

    echo join(' | ', $textarray);
    echo '</div>';
}

?>

</body>
</html>
