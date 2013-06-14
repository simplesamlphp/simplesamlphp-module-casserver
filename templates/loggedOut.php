<?php

assert('is_string($this->data["url"])');

$this->data['header'] = $this->t('{sbcasserver:loggedout:loggedout_header}');

$this->includeAtTemplateBase('includes/header.php');
?>

    <p>
        <?php
        echo $this->t('{sbcasserver:loggedout:loggedout_description}')
        ?>

<?php

$this->includeAtTemplateBase('includes/footer.php');

if (isset($this->data['autofocus'])) {
    echo '<script type="text/javascript">window.onload = function() {document.getElementById(\'' . $this->data['autofocus'] . '\').focus();}</script>';
}