<?php
$text = file_get_contents('c:\\laragon\\www\\real-rent-car-main\\resources\\views\\admin\\contracts\\pdf.blade.php');
$fixed = mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
echo substr($fixed, 0, 500);
