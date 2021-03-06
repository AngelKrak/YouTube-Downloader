<?php

namespace YoutubeDownloader;

/**
 * YouTubeDownloader
 */
class YoutubeDownloader
{
	/**
	 * Validates a video ID
	 *
	 * This can be an url, embedding url or embedding html code
	 *
	 * @param string $video_id
	 * @return string|null The validated video ID or null, if the video ID is invalid
	 */
	public static function validateVideoId($video_id)
	{
		if (strlen($video_id) <= 11)
		{
			return $video_id;
		}

		if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $video_id, $match))
		{
			if (is_array($match) && count($match) > 1)
			{
				return $match[1];
			}
		}

		return null;
	}

	/**
	 * Check if a string is from mobile ulr
	 *
	 * @param string $string
	 * @return bool
	 */
	public static function isMobileUrl($string)
	{
		if (strpos($string, "m."))
		{
			return true;
		}

		return false;
	}

	/**
	 * @param string $string
	 * @return string
	 */
	public static function treatMobileUrl($string)
	{
		return str_replace("m.", "www.");
	}

	/**
	 * @param int $bytes
	 * @param int $precision
	 * @return string
	 */
	public static function formatBytes($bytes, $precision = 2)
	{
		$units = ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
		$bytes = max($bytes, 0);
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
		$pow = min($pow, count($units) - 1);
		$bytes /= pow(1024, $pow);

		return round($bytes, $precision) . '' . $units[$pow];
	}

	/**
	 * @return bool
	 */
	public static function is_chrome()
	{
		$agent = $_SERVER['HTTP_USER_AGENT'];

		// if user agent is google chrome
		if (preg_match("/like\sGecko\)\sChrome\//", $agent))
		{
			// but not Iron
			if (!strstr($agent, 'Iron'))
			{
				return true;
			}
		}

		// if isn't chrome return false
		return false;
	}

	/**
	 * function to get via cUrl
	 *
	 * From lastRSS 0.9.1 by Vojtech Semecky, webmaster @ webdot . cz
	 * See http://lastrss.webdot.cz/
	 *
	 * @param string $url
	 * @return string
	 */
	public static function curlGet($URL)
	{
		global $config; // get global $config to know if $config['multipleIPs'] is true

		$ch = curl_init();
		$timeout = 3;

		if ($config['multipleIPs'] === true)
		{
			// if $config['multipleIPs'] is true set outgoing ip to $outgoing_ip
			global $outgoing_ip;
			curl_setopt($ch, CURLOPT_INTERFACE, $outgoing_ip);
		}

		curl_setopt($ch, CURLOPT_URL, $URL);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);

		/* if you want to force to ipv6, uncomment the following line */
		//curl_setopt( $ch , CURLOPT_IPRESOLVE , 'CURLOPT_IPRESOLVE_V6');
		$tmp = curl_exec($ch);
		curl_close($ch);

		return $tmp;
	}

	/**
	 * @param string $url
	 * @return string
	 */
	public static function get_size($url)
	{
		global $config;

		$my_ch = curl_init($url);

		if ($config['multipleIPs'] === true)
		{
			global $outgoing_ip;
			curl_setopt($my_ch, \CURLOPT_INTERFACE, $outgoing_ip);
		}

		curl_setopt($my_ch, \CURLOPT_HEADER, true);
		curl_setopt($my_ch, \CURLOPT_NOBODY, true);
		curl_setopt($my_ch, \CURLOPT_RETURNTRANSFER, true);
		curl_setopt($my_ch, \CURLOPT_TIMEOUT, 10);
		curl_setopt($my_ch, \CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($my_ch, \CURLOPT_SSL_VERIFYPEER, 0);
		$r = curl_exec($my_ch);

		foreach (explode("\n", $r) as $header)
		{
			if (strpos($header, 'Content-Length:') === 0)
			{
				return trim(substr($header, 16));
			}
		}

		return '';
	}

	/**
	 * @param array $avail_formats
	 * @param string $format
	 * @return bool
	 */
	public static function getDownloadUrlByFormats(array $avail_formats, $format)
	{
		$target_formats = '';

		switch ($format)
		{
			case "best":
				/* largest formats first */
				$target_formats = ['38', '37', '46', '22', '45', '35', '44', '34', '18', '43', '6', '5', '17', '13'];
				break;
			case "free":
				/* Here we include WebM but prefer it over FLV */
				$target_formats = ['38', '46', '37', '45', '22', '44', '35', '43', '34', '18', '6', '5', '17', '13'];
				break;
			case "ipad":
				/* here we leave out WebM video and FLV - looking for MP4 */
				$target_formats = ['37', '22', '18', '17'];
				break;
			default:
				/* If they passed in a number use it */
				if (is_numeric($format))
				{
					$target_formats[] = $format;
				}
				else
				{
					$target_formats = ['38', '37', '46', '22', '45', '35', '44', '34', '18', '43', '6', '5', '17', '13'];
				}
				break;
		}

		/* Now we need to find our best format in the list of available formats */
		$best_format = '';

		for ($i = 0; $i < count($target_formats); $i++)
		{
			for ($j = 0; $j < count($avail_formats); $j++)
			{
				if ($target_formats[$i] == $avail_formats[$j]['itag'])
				{
					//echo '<p>Target format found, it is '. $avail_formats[$j]['itag'] .'</p>';
					$best_format = $j;
					break 2;
				}
			}
		}

		$redirect_url = null;

		if (
			(isset($best_format)) &&
			(isset($avail_formats[$best_format]['url'])) &&
			(isset($avail_formats[$best_format]['type']))
		)
		{
			$redirect_url = $avail_formats[$best_format]['url'] . '&title=' . $cleanedtitle;
			$content_type = $avail_formats[$best_format]['type'];
		}

		return $redirect_url;
	}
	
	public static function getDownloadMP3($video_id)
	{
		global $config;
		// generate new url, we can re-use previously generated link and pass it to "token" parameter but it too dangerous to use it with exec()
		// TODO: Background conversion, Ajax and Caching
		// @ewwink
		$video_info_url = 'https://www.youtube.com/get_video_info?&video_id=' . $video_id. '&asv=3&el=detailpage&hl=en_US';
		$video_info_string = self::curlGet($video_info_url);
		$video_info = \YoutubeDownloader\VideoInfo::createFromString($video_info_string);
		$stream_map = \YoutubeDownloader\StreamMap::createFromVideoInfo($video_info);
		$audio_quality = 0;
		$media_url = "";
		$media_type = "";

		$formats = $stream_map->getFormats();
		// find audio with highest quality
		foreach($formats as $format)
		{
			if(strpos($format['type'], 'audio') !== false && intval($format['quality']) > intval($audio_quality))
			{
				$audio_quality = $format['quality'];
				$media_url = $format['url'];
				$media_type = str_replace("audio/", "", $format['type']);
			}
		}
		
		if(empty($media_url))
		{	
			if($config['MP3ConvertVideo'] === true )
			{
				// some video does not have adaptive or dash format, downloading video instead
				$formats = $stream_map->getStreams();
				$media_url = $formats[0]['url'];
				$media_type = str_replace("audio/", "", $formats[0]['type']);
			}
			else
			{
				return array("status" => "failed",
						 "message" => "Failed, adaptive audio format not available, try to set <strong>\$config['MP3ConvertVideo'] = true;</strong>");
			}
		}

		$mp3dir = realpath($config['MP3TempDir']);
		$mediaName = $_GET['title'] . '.' . $media_type;
		// -x4: set 4 connection for each download
		$cmd = '"' . $config['aria2Path'] . '"' . " -x4 -k1M --continue=true --dir=\"$mp3dir\" --out=$mediaName \"$media_url\" 2>&1" ;
		exec($cmd, $output);

		if(strpos(implode(" ", $output), "download completed") !== FALSE)
		{
			// Download media from youtube success
			$mp3Name = $_GET['title'] . '.mp3';
			if($config['MP3Quality'] !== "high" || $audio_quality === 0)
			{
				$audio_quality = intval($config['MP3Quality']) > intval($audio_quality) ? $audio_quality : $config['MP3Quality'];
			}
			
			$cmd = '"' . $config['ffmpegPath'] . '"' . " -i \"$mp3dir/$mediaName\" -b:a $audio_quality -vn \"$mp3dir/$mp3Name\" 2>&1";
			
			exec($cmd, $output);
			if(strpos(implode(" ", $output), "Output #0, mp3") !== FALSE || file_exists("$mp3dir/$mp3Name"))
			{
				// Convert media to .mp3 success
				return array("status" => "success",
								"message" => "Convert media to .mp3 success",
								"mp3" => "$mp3dir/$mp3Name",
								"debugMessage" => $output);
			}
		}
		else
		{
			return array("status" => "failed",
							"message" => "Download media url from youtube failed.",
							"debugMessage" => $output);
		}
	}
}
