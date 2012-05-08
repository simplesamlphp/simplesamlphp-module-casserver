<?php
if(!array_key_exists('header', $this->data)) {
	$this->data['header'] = 'selectidp';
}
$this->data['header'] = $this->t($this->data['header']);
$this->data['header'] = "Vælg login metode";
$this->data['rememberenabled'] = false;

//Don't use the preferredidp stuff.
//$this->data['autofocus'] = 'preferredidp';
$this->data['preferredidp'] = '';

$this->includeAtTemplateBase('includes/header.php');

foreach ($this->data['idplist'] AS $idpentry) {
	if (isset($idpentry['name'])) {
		$this->includeInlineTranslation('idpname_' . $idpentry['entityid'], $idpentry['name']);
	} elseif (isset($idpentry['OrganizationDisplayName'])) {
		$this->includeInlineTranslation('idpname_' . $idpentry['entityid'], $idpentry['OrganizationDisplayName']);
	}
	if (isset($idpentry['description']))
		$this->includeInlineTranslation('idpdesc_' . $idpentry['entityid'], $idpentry['description']);
}
?>
		<h2>
                  <div id="chooseHeadingText">Log på</div>
                </h2>

		<form method="get" action="<?php echo $this->data['urlpattern']; ?>">
		<input type="hidden" name="entityID" value="<?php echo htmlspecialchars($this->data['entityID']); ?>" />
		<input type="hidden" name="return" value="<?php echo htmlspecialchars($this->data['return']); ?>" />
		<input type="hidden" name="returnIDParam" value="<?php echo htmlspecialchars($this->data['returnIDParam']); ?>" />
		
		<?php

		$idpindex = 0;
		foreach ($this->data['idplist'] AS $idpentry) {
			if ($idpentry['entityid'] != $this->data['preferredidp']) {

				
				echo "\n" . '	<h3 style="margin-top: 8px">';

				echo('<input class="buttonAsLink" type="submit" name="idp_' .
					 htmlspecialchars($idpentry['entityid']) . '" value="' .  
					 htmlspecialchars($this->t('idpname_' . $idpentry['entityid'])). '" />');


				/* Acquire Danish and English translations of idp-header/title */
				$headerTag ='idpname_' . $idpentry['entityid'];
				$headerTranslations = $this->getTag($headerTag);
				if($headerTranslations === NULL) {
					/* Tag not found. */
					/* SimpleSAML_Logger::info('Template: Looking up [' . $headerTag . ']: not translated at 
all.');
					return $this->t_not_translated($headerTag, $fallbackdefault);
					*/
				}

				/* Retrieve for english translation. */
				if(array_key_exists('en', $headerTranslations)) {
					$englishHeader = $headerTranslations['en'];
				} else {
					$englishHeader = "";
				}

				/* Retrieve for danish translation. */
				if(array_key_exists('da', $headerTranslations)) {
					$danishHeader = $headerTranslations['da'];
				} else {
					$danishHeader = "";
				}


                                /* Acquire Danish and English translations of idp-description */
                                $descriptionTag ='idpdesc_' . $idpentry['entityid'];
                                $descriptionTranslations = $this->getTag($descriptionTag);
                                if($descriptionTranslations === NULL) {
                                        /* Tag not found. */
                                        /* SimpleSAML_Logger::info('Template: Looking up [' . $descriptionTag . ']: not translated 
at
all.');
                                        return $this->t_not_translated($descriptionTag, $fallbackdefault);
                                        */
                                }

                                /* Retrieve for english translation. */
                                if(array_key_exists('en', $descriptionTranslations)) {
                                        $englishDescription = $descriptionTranslations['en'];
                                } else {
                                        $englishDescription = "";
                                }

                                /* Retrieve for danish translation. */
                                if(array_key_exists('da', $descriptionTranslations)) {
                                        $danishDescription = $descriptionTranslations['da'];
                                } else {
                                        $danishDescription = "";
                                }





				if(array_key_exists('icon', $idpentry) && $idpentry['icon'] !== NULL) {
					$iconUrl = SimpleSAML_Utilities::resolveURL($idpentry['icon']);
					echo '&nbsp;<img width="32" src="' . htmlspecialchars($iconUrl) . '" />';
				}

				echo '</h3>';


				/* Here English and Danish translations are placed in html, outcommented */

				echo("<div id='englishIdpHeader" . $idpindex . "' style='display: none;'>"
					. htmlspecialchars($englishHeader) . "</div>");
				echo("<div id='danishIdpHeader" . $idpindex . "' style='display: none;'>"
					. htmlspecialchars($danishHeader) . "</div>");

				echo("<div id='englishIdpDescription" . $idpindex . "' style='display: none;'>"
					. htmlspecialchars($englishDescription) . "</div>");
				echo("<div id='danishIdpDescription" . $idpindex . "' style='display: none;'>"
					. htmlspecialchars($danishDescription) . "</div>");

				/* Here is provided the space for javascipt to dump the right description translation */
				//if (!empty($idpentry['description'])) {
				//echo '<p>';
				echo("<div id='idpDescription" . $idpindex . "'></div>");
				echo '<br />';
				//echo '</p>';
				//}
			}
			$idpindex = $idpindex + 1;
		}
		/* Insert number of idps, so that javascript can later iterate over them */
		echo("<div id='numberOfIdps'><!--" . $idpindex . "--></div>");
		
		?>
		</form>
</div>

<div id="content">
		<h2><div id="descriptionHeadingText">Hjælp</div></h2>
		<p style="text-align: justify;"><div id="descriptionText"></div></p>
</div>
<!--div id="content">
<div class="panel" style="display:none;">
<p><?php echo $this->t('{sbdisco:discopage:moredescription}'); ?></p>
</div-->

    <script type="text/javascript">
      goDanish();
    </script>
 
<!--h2 class="flip"><?php echo $this->t('{sbdisco:discopage:moredescription_heading_show_more}')?></h2-->
 
</div>
		
<?php $this->includeAtTemplateBase('includes/footer.php'); ?>
