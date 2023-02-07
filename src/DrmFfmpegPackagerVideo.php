<?php

namespace yvesniyo\VideoFfmpegShaka;

use Exception;
use Shaka\Options\Streams\HLSStream;
use FFMpeg\FFMpeg;
use Shaka\Shaka;
use Shaka\Options\DRM\Raw as ShakaDrmRaw;
use SplFileInfo;
use FFMpeg\Coordinate\Dimension;
use FFMpeg\Filters\Video\ResizeFilter;
use FFMpeg\Format\Video\X264;
use FFMpeg\Media\Video;


class DrmFfmpegPackagerVideo
{

    public FFMpeg $ffmpeg;
    public Shaka $shaka;

    public array $resolutions = [
        "144p"  => [256,  144, 250],
        "240p"  => [426,  240, 500],
        "360p"  => [640,  360, 1000],
        "480p"  => [854,  480, 2400],
        "720p"  => [1280, 720, 4800],
        "1080p" => [1920, 1080, 8000],
        "2k"    => [2560, 1440, 6144],
        "4k"    => [3840, 2160, 17408],
    ];

    public function __construct(array $config = [])
    {
        $this->ffmpeg = FFMpeg::create([
            'ffmpeg.binaries'   => $config["ffmpeg.binaries"],
            'ffprobe.binaries'  => $config["ffprobe.binaries"],
            'ffmpeg.threads'    => $config["ffmpeg.threads"],   // The number of threads that FFMpeg should use,
            'timeout'           => $config["ffmpeg.timeout"], // The timeout for the underlying process
        ]);

        $this->shaka = Shaka::initialize([
            'packager.binaries' => $config["packager.binaries"],
            'timeout'           => $config["packager.timeout"], // The timeout for the underlying process
        ]);
    }


    public function setResolutions(array $resolutions)
    {
        //validate the resolutions input
        array_walk($resolutions, function ($resolution) {

            if (count($resolution) != 3) {

                throw new Exception("Resolution should have three parameters");
            }

            array_walk($resolution, function ($parm) {

                if (!is_int($parm) || $parm <= 0) {

                    throw new Exception("Resolution parameters should integer and be greater than 0");
                }
            });
        });


        // assign the new resolutions
        $this->resolutions = $resolutions;

        return $this;
    }

    public function export(string $fileToProcess, string $outputPath, string $keys = null)
    {
        if (!file_exists($fileToProcess)) {

            die("$fileToProcess does not exist");
        }

        $outputPath = $this->makeOutputPath($fileToProcess, $outputPath);

        $videos = $this->convertVideosIntoDifferentResolutions($fileToProcess, $outputPath);

        $streams = $this->generateStreams($videos, $outputPath);

        $hls = $outputPath . 'output/h264_master.m3u8';
        $dash = $outputPath . 'output/h264.mpd';

        $result =  $this->shaka->streams(...$streams)
            ->mediaPackaging()
            ->HLS($hls)
            ->DASH($dash);

        if (!is_null($keys)) {

            $result->DRM('raw', function (ShakaDrmRaw $options) use ($keys) {

                return $options->keys($keys)
                    ->enableRawKeyDecryption()
                    ->pssh('000000317073736800000000EDEF8BA979D64ACEA3C827DCD51D21ED00000011220F7465737420636F6E74656E74206964');
            });
        }

        $result->export();

        return [
            "path" => $outputPath . "output",
            "hls" => $hls,
            "dash" => $dash
        ];
    }

    private function makeOutputPath(string $fileToProcess, string $outputPath)
    {

        if (!str_ends_with($outputPath, "/")) {
            $outputPath .= "/";
        }

        $videoInputName = (new SplFileInfo($fileToProcess))->getFilename();
        $videoInputName = substr(str_replace(" ", "_", substr($videoInputName, 0, strpos($videoInputName, ".", 1))), 0, 70);
        $randomVideoId = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));
        $outputPath = "{$outputPath}{$randomVideoId}_{$videoInputName}/";

        if (!file_exists($outputPath)) {
            mkdir($outputPath, 777, true);
        }

        return $outputPath;
    }

    private function generateStreams(array $videos, string $outputPath)
    {

        $videoOutput = $outputPath . "output/";
        if (!file_exists($videoOutput)) {
            mkdir($videoOutput, 777, true);
        }

        $streams = [];

        foreach ($videos as $videoQuality => $inputVideoPath) {

            // make a stream  of audio from the first video
            if ($videoQuality == array_keys($this->resolutions)[0]) {

                $streamAudio = HLSStream::input($inputVideoPath)
                    ->streamSelector('audio')
                    ->output($videoOutput . 'audio.mp4')
                    ->playlistName('audio.m3u8')
                    ->HLSGroupId('audio')
                    ->HlsName('ENGLISH');

                $streams[] = $streamAudio;
            }

            $streamVideo = HLSStream::input($inputVideoPath)
                ->streamSelector("video")
                ->output($videoOutput . "h264_{$videoQuality}.mp4")
                ->playlistName("h264_{$videoQuality}.m3u8")
                ->iframeplaylistName("h264_{$videoQuality}_iframe.m3u8");


            $streams[] = $streamVideo;
        }

        return $streams;
    }

    private function convertVideosIntoDifferentResolutions(string $fileToProcess, string $outputPath)
    {



        $resolutionsPath =  $outputPath . "resolutions/";
        if (!file_exists($resolutionsPath)) {
            mkdir($resolutionsPath, 777, true);
        }

        $videos = [];

        /** @var Video */
        $videoInput = $this->ffmpeg->open($fileToProcess);


        foreach ($this->resolutions as $key => $size) {


            $videoInput->filters()
                ->resize(new Dimension($size[0], $size[1]), ResizeFilter::RESIZEMODE_FIT)
                ->synchronize();

            $videoToSave = "{$resolutionsPath}video_{$key}.mp4";


            $videoInput->save((new X264())->setKiloBitrate($size[2]), $videoToSave);

            $videos[$key] = $videoToSave;
        }

        return $videos;
    }
}
