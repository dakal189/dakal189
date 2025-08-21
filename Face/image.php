<?php
require 'FaceDetector.php';

use svay\FaceDetector;

$faceDetect = new FaceDetector();
$faceDetect->faceDetect($_GET['img']);
$faceDetect->toJpeg();