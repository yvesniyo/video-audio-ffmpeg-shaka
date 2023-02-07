<?php
require '../vendor/autoload.php';

use yvesniyo\VideoFfmpegShaka\DrmFfmpegPackagerVideo;

$fileToProcess = 'D:\Entertainment\Songs\Zaalima full video song _ Raees _ Shah Rukh Khan _ Mahira Khan _ Arijit Singh _ Harshdeep Kaur.mp4';
$output_path = "./outputs";


$drmFfmpegPackagerVideo = new  DrmFfmpegPackagerVideo([
    'ffmpeg.binaries'   => 'C:/Program Files/ffmpeg/bin/ffmpeg.exe',
    'ffprobe.binaries'  => 'C:/Program Files/ffmpeg/bin/ffprobe.exe',
    'ffmpeg.threads'    => 12,   // The number of threads that FFMpeg should use,
    'packager.binaries' => "C:/Program Files/Packager/packager.exe",
    'packager.timeout'  => 360000, // The timeout for the underlying process
    'ffmpeg.timeout'    => 360000, // The timeout for the underlying process
]);

$drmFfmpegPackagerVideo->setResolutions([
    "144p"  => [256,  144, 250],
    // "240p"  => [426,  240, 500],
    // "360p"  => [640,  360, 1000],
    // "480p"  => [854,  480, 2400],
    // "720p"  => [1280, 720, 4800],
    // "1080p" => [1920, 1080, 8000],
    // "2k"    => [2560, 1440, 6144],
    // "4k"    => [3840, 2160, 17408],
]);


$result = $drmFfmpegPackagerVideo->export(
    $fileToProcess,
    $output_path,
    file_get_contents('raw.key')
);

print_r($result);
