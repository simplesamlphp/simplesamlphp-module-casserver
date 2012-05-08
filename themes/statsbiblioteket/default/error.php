<?php 
	$this->data['header'] = $this->t('error_header');
	
	$this->data['head'] = '
<meta name="robots" content="noindex, nofollow" />
<meta name="googlebot" content="noarchive, nofollow" />';
	
	$this->includeAtTemplateBase('includes/header.php'); 
?>


		<h2><?php echo $this->t('{sbcasserver:discopage:error_heading}'); ?></h2>
		<p style="text-align: justify;"><?php echo $this->t('{sbcasserver:discopage:error_description}'); ?></p>

<!--
		TRACK ID: <?php echo $this->data['error']['trackId']; ?>
-->
		

<!--
	   MESSAGE: <?php echo htmlspecialchars($this->data['error']['exceptionMsg']); ?>
		TRACE: <?php echo htmlspecialchars($this->data['error']['exceptionTrace']); ?>
		</div>
-->

<?php $this->includeAtTemplateBase('includes/footer.php'); ?>
