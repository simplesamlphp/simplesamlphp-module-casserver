<?php

assert('is_string($this->data["url"])');

$this->data['header'] = $this->t('{sbcasserver:sbcasserver:loggedout_header}');

$this->includeAtTemplateBase('includes/header.php');
?>

    <p>
        <?php
        echo $this->t('{sbcasserver:sbcasserver:loggedout_description}')
        ?>
    </p>

    <p>
        <a href=<?php echo($this->data["url"]) ?>><?php $this->t('{sbcasserver:sbcasserver:continue_header}') ?></a>
    </p>

<?php

$this->includeAtTemplateBase('includes/footer.php');

if (isset($this->data['autofocus'])) {
    echo '<script type="text/javascript">window.onload = function() {document.getElementById(\'' . $this->data['autofocus'] . '\').focus();}</script>';
}