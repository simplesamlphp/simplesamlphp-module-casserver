$(document).ready(function () {
    $('.saml-response').each((idx, elem) => {
        var $xmlText = $(elem).attr('value')
        var xmlTextPretty = vkbeautify.xml($xmlText);
        elem.textContent = xmlTextPretty;
        hljs.highlightElement(elem);
    });
});
