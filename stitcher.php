#!/usr/bin/php
<?php

function getExtensionToFunctionMapping()
{
	static $mappings = null;
	
	if($mappings === null)
	{
		$mappings = array(
			// Windows Bitmap
			'bmp' => 'wbmp',
			'dib' => 'wbmp',
			// gif
			'gif' => 'gif',
			// JPEG
			'jfif' => 'jpeg',
			'jpe' => 'jpeg',
			'jpeg' => 'jpeg',
			'jpg' => 'jpeg',
			// PNG
			'png' => 'png'
		);
	}
	
	return $mappings;
}

function getOption($key)
{
	static $options = null;
	
	if($options === null)
	{
		$options = array_merge(
			array(
				"d" => getcwd(),
				"f" => "jpg",
				"o" => "./merged-wallpaper.jpg"
			),
			getopt("d:f:o:")
		);
	}
	
	return $options[$key];
}

function getScreenInfo()
{
	static $screenInfo = null;
	
	if($screenInfo === null)
	{
		$screenInfo = json_decode(exec('screeninfo.exe'));
		
	}
	
	return $screenInfo;
}

function parseScreenDimensions($screen)
{
	return [
		"x" => $screen->Bounds->X,
		"y" => $screen->Bounds->Y,
		"w" => $screen->Bounds->Width,
		"h" => $screen->Bounds->Height
	];
}

function getExtension($fileName)
{
	return substr($fileName, strrpos($fileName, ".") + 1);
}

function getFunctionNameForFileType($extension, $prefix)
{
	$mappings = getExtensionToFunctionMapping();
	
	return isset($mappings[$extension])
		? $prefix . $mappings[$extension]
		: null;
}

function getCreateFunctionNameForFileType($extension)
{
	return getFunctionNameForFileType($extension, 'imagecreatefrom');
}

function getSaveFunctionNameForFileType($extension)
{
	return getFunctionNameForFileType($extension, 'image');
}

function getWallpaperPath($fileName)
{
	$path = getOption("d");
	
	if(substr($path, -1) !== "/")
	{
		$path .= "/";
	}
	
	return $path . $fileName;
}

function getValidFileExtensions()
{
	return array_keys(getExtensionToFunctionMapping());
}

function getFiles($filter = null)
{
	$files = preg_filter(
		"/^(.*?\.(" . implode("|", getValidFileExtensions()) . "))$/",
		"$1",
		scandir(getOption("d"))
	);
	
	if($files === null)
	{
		return [];
	}
	else
	{
		if($filter !== null)
		{
			$files = array_filter($files, $filter);
		}
		
		// make the keys continual from 0 up
		return array_slice($files, 0);
	}
}

function println()
{
	$args = func_get_args();
	
	if(count($args) == 1)
	{
		echo $args[0];
	}
	elseif(count($args) > 1)
	{
		call_user_func_array('printf', $args);
	}
	echo PHP_EOL;
}
function printerr($text, $line = -1)
{
	println("ERROR: %s %s", $text, ($line > -1) ? "in line " . $line : "");
}
function debug($text = "")
{
	$args = func_get_args();
	
	$args[0] = "DEBUG: " . (count($args) == 0 ? "" : $args[0]);
	
	call_user_func_array('println', $args);
}

function getUserChoiceFromList($list, $message = "There are several choices: ")
{
	if(!is_array($list) || count($list) == 0)
	{
		printerr("got empty \$list", __LINE__);
		return null;
	}
	
	println($message);
	
	$maxNumberLength = strlen((string) count($list));
	
	while(true)
	{
		foreach ($list as $key => $value)
		{
			println("%" . ($maxNumberLength + 2) ."s %s", "[" . $key . "]", $value);
		}
		
		print("which one do you want to use? ");
		
		$cli = fopen("php://stdin", "r");
		$userInput = (int) fgets($cli);
		fclose($cli);
		
		if($userInput > -1 && $userInput < count($list))
		{
			return $list[$userInput];
		}
		else
		{
			var_dump($userInput);
			println("Sorry, invalid choice. Try again:");
		}
	}
}

function getTotalSize()
{
	static $totalSize = null;
	
	if($totalSize === null)
	{
		$edges = array(
			"left" => 0,
			"right" => 0,
			"top" => 0,
			"bottom" => 0
		);
		
		array_map(function($screen) use (&$edges)
		{
			$dimensions = parseScreenDimensions($screen);
			
			$edges["left"]   = min($edges["left"],   $dimensions["x"]);
			$edges["right"]  = max($edges["right"],  $dimensions["x"] + $dimensions["w"]);
			$edges["top"]    = min($edges["top"],    $dimensions["y"]);
			$edges["bottom"] = max($edges["bottom"], $dimensions["y"] + $dimensions["h"]);
		}, getScreenInfo());
		
		$totalSize = array(
			"width" => $edges["right"] - $edges["left"],
			"height" => $edges["bottom"] - $edges["top"]
		);
		
	}
	
	return $totalSize;
}

function getCanvas()
{
	static $canvas = null;
	
	if($canvas === null)
	{
		$totalSize = getTotalSize();
		$canvas = imagecreatetruecolor($totalSize["width"], $totalSize["height"]);
		imagefill($canvas, 0, 0, imagecolorallocate($canvas, 0, 0, 0));
	}
	
	return $canvas;
}

function getUserChoice()
{
	$println_args = array();
	$callbacks = null;
	array_map(function($value) use (&$callbacks, &$println_args)
	{
		if(is_array($value) && $callbacks === null)
		{
			$callbacks = $value;
		}
		elseif(is_string($value) || count($println_args) > 0)
		{
			array_push($println_args, $value);
		}
		else
		{
			printerr("invalid arguments for function getUserChoice");
			exit();
		}
	}, func_get_args());
	
	if($callbacks === null || count($println_args) < 1)
	{
		printerr("invalid arguments for function getUserChoice");
		exit();
	}
	
	$options = array_keys($callbacks);
	$sortedOptions = array_slice($options, 0);
	
	usort($sortedOptions, function($a, $b)
	{
		return strlen($a) - strlen($b);
	});
	
	$shortcuts = array();
	
	$formattedOptions = array_map(function($value) use (&$shortcuts, &$options)
	{
		for($i = 1, $l = strlen($value); $i < $l; $i++)
		{
			$shortcut = substr($value, 0, $i);
			if(!array_key_exists($shortcut, $shortcuts) && !in_array($shortcut, $options))
			{
				$shortcuts[$shortcut] = $value;
				return "[" . $shortcut . "]" . substr($value, strlen($shortcut));
			}
		}
		
		return $value;
		
	}, $sortedOptions);
	
	usort($formattedOptions, function($a, $b) use ($options) {
		return array_search($a, $options) - array_search($b, $options);
	});
	
	$message = call_user_func_array('sprintf', $println_args);
	printf("%s (%s): ", $message, implode(", ", $formattedOptions));
	
	while(true)
	{
		$cli = fopen("php://stdin", "r");
		$userInput = trim(fgets($cli));
		fclose($cli);
		
		if(array_key_exists($userInput, $shortcuts))
		{
			$userInput = $shortcuts[$userInput];
		}
		
		if(array_key_exists($userInput, $callbacks))
		{
			return $callbacks[$userInput]();
		}
		else
		{
			println("Sorry, invalid choice. Try again (%s)", implode(", ", $formattedOptions));
		}
	}
	
}

function saveFile()
{
	if(call_user_func(getSaveFunctionNameForFileType(getOption("f")), getCanvas(), getOption("o")))
	{
		println("Saved file to " . getOption("o"));
	}
	else
	{
		printerr("Could not save file to " . getOption("o"));
		exit();
	}
}

array_map(
	function($screen) {
		
		$dimensions = parseScreenDimensions($screen);
		
		$dimensionString = $dimensions["w"] . "x" . $dimensions["h"];
		
		if(count(getFiles()) == 0)
		{
			println("Could not find any images in folder %s", getOption("d"));
			println("Please specify another folder with parameter -d");
			exit();
		}
		
		$matches = getFiles(function($k) use ($dimensionString) {
			return !(false === stripos($k, $dimensionString));
		});
		
		
		switch(count($matches))
		{
			case 0:
				println("Could not find an image for screen \"%s\" (%s)", $screen->DeviceName, $dimensionString);
				$wallpaper = getUserChoiceFromList(getFiles(), "Please choose an alternative image:");
				break;
			case 1:
				$wallpaper = $matches[0];
				break;
			default:
				$wallpaper = getUserChoiceFromList($matches, sprintf("there are multiple options for screen \"%s\" (%s):", $screen->DeviceName, $dimensionString));
				break;
		}
		
		println("Using %s for display \"%s\" (%s)", $wallpaper, $screen->DeviceName, $dimensionString);
		
		$imageCreationFunction = getCreateFunctionNameForFileType(getExtension($wallpaper));
		if($imageCreationFunction === null)
		{
			printerr("Invalid file format: " . $wallpaper);
			exit();
		}
		else
		{
			$screenCanvas = call_user_func($imageCreationFunction, getWallpaperPath($wallpaper));
			
			$totalSize = getTotalSize();
			$dstX = ($dimensions["x"] < 0) ? $dimensions["x"] + $totalSize["width"] : $dimensions["x"];
			$dstY = ($dimensions["y"] < 0) ? $dimensions["y"] + $totalSize["height"] : $dimensions["y"];
			
			$actualWallpaperSize = [
				"width" => imagesx($screenCanvas),
				"height" => imagesy($screenCanvas)
			];
			
			if($actualWallpaperSize["width"] != $dimensions["w"] || $actualWallpaperSize["height"] != $dimensions["h"])
			{
				getUserChoice("Size of %s (%sx%s) does not fit screen %s (%s). Should the image be scaled?",
						$wallpaper, $actualWallpaperSize["width"], $actualWallpaperSize["height"], $screen->DeviceName, $dimensionString, [
					"yes" => function() use ($screenCanvas, $dimensions, $actualWallpaperSize)
					{
						imagecopyresampled($screenCanvas, $screenCanvas, 0, 0, 0, 0, $dimensions["w"], $dimensions["h"], $actualWallpaperSize["width"], $actualWallpaperSize["height"]);
					},
					"no" => function() {}
				]);
			}
			
			$heightUpperPart = $totalSize['height'] - $dstY;
			$heightLowerPart = $dimensions["h"] - $heightUpperPart;
			// copy upper part until lower edge
			imagecopy(getCanvas(), $screenCanvas, $dstX, $dstY, 0, 0, $dimensions["w"], $heightUpperPart);
			// copy lower part from top edge
			imagecopy(getCanvas(), $screenCanvas, $dstX, 0, 0, $heightUpperPart, $dimensions["w"], $heightLowerPart);
		}
	},
	getScreenInfo()
);


if(null === getSaveFunctionNameForFileType(getOption("f")))
{
	printerr("Invalid output file format (-f parameter)");
	exit();
}
else
{
	if(file_exists(getOption("o")))
	{
		getUserChoice("file \"%s\" does already exist. Do you want to override? ", getOption("o"), [
			"override" => function() {
				saveFile();
			},
			"cancel" => function() {
				exit();
			}
		]);
	}
	else
	{
		saveFile();
	}
}

