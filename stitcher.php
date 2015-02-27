#!/usr/bin/php
<?php

/**
 *  Multiscreen Wallpaper Patcher
 *  
 *  Command line script that calls a custom Windows application that returns 
 *  information about all active monitors (resolution, positioning). According 
 *  to the gathered information the script looks for image files with these 
 *  resolutions in the file name (e.g. wallpaper-1920x1080.jpg).
 *  These image files are stitched together to one big image file that can be 
 *  used as tiling wallpaper.
 *  
 *  @author    Andreas Mischke <andreas@mischke.me>
 *  @see       https://github.com/andreasmischke/win7-wallpaper-stitcher
 *  @license   © 2015 Licensed under WTFPL – http://www.wtfpl.net/
 *  @version   1.0
 */



/**
 *  Returns an array to map file endings to the appropriate fraction of the 
 *  PHP GD library functions (these are e.g. imagejpeg() for JPG, imagepng() 
 *  for PNG).
 *  
 *  The mapping array could also be defined globally, but to retain the 
 *  functional programming style it is encapsulated in a function.
 *  
 *  @return string[] Mapping von Dateiendungen zu PHP-Funktionsnamen-
 *          Bruchteilen
 */
function getExtensionToFunctionMapping()
{
	// with the use of `static` the variable survives a single function call.
	// So `$mapping` is `null` with the first call and the if block is executed,
	// e.g. `$mapping` gets defined. From then on `$mapping` keeps its value and
	// just gets returned with all further calls without being redefined every
	// time.
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

/**
 * Returns the value of the specified option. The value is defined by command 
 * line parameters if present or with an default/fallback parameter.
 * 
 * @param string $key option key
 * 
 * @return string option value
 */
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

/**
 * Wrapper for the call of the Windows command line application. The result is
 * cached so the application has to be run only once.
 * 
 * @return object[] array of screen info objects
 */
function getScreenInfo()
{
	static $screenInfo = null;
	
	if($screenInfo === null)
	{
		$screenInfo = json_decode(exec('screeninfo.exe'));
		
	}
	
	return $screenInfo;
}

/**
 * Extracts the X-, Y-position, height and width from the screen info object.
 * Was needed in an earlier version of the script since the information was in 
 * a string value. The current version only restructures the object properties 
 * into an array.
 * 
 * @param object $screen screen information as returned by `getScreenInfo()`
 * 
 * @return string[] array with X-, Y-position, height and width of the 
 *                  specified screen
 */
function parseScreenDimensions($screen)
{
	return [
		"x" => $screen->Bounds->X,
		"y" => $screen->Bounds->Y,
		"w" => $screen->Bounds->Width,
		"h" => $screen->Bounds->Height
	];
}

/**
 * @param the filename from which the extension shall be retrieved
 * 
 * @return the part after the last dot, i.e. the file type extension
 */
function getExtension($fileName)
{
	return substr($fileName, strrpos($fileName, ".") + 1);
}

/**
 * Builds PHP GD library function names from a common denominator and file type 
 * specific suffixes.
 * 
 * @param string $extension The extension for which the suffix should be 
 *                          determined. There must be a mapping for the suffix 
 *                          in the function `getExtensionToFunctionMapping()`.
 * @param string $prefix    The common denominator.
 * 
 * @return string function name
 */
function getFunctionNameForFileType($denominator, $extension)
{
	$mappings = getExtensionToFunctionMapping();
	
	return isset($mappings[$extension])
		? $denominator . $mappings[$extension]
		: null;
}

/**
 * Returns the file type specific function to create an image object from an 
 * existing file.
 * 
 * @param string $extension The extension for which the suffix should be 
 *                          determined. There must be a mapping for the suffix 
 *                          in the function `getExtensionToFunctionMapping()`.
 * 
 * @return string function name
 */
function getCreateFunctionNameForFileType($extension)
{
	return getFunctionNameForFileType('imagecreatefrom', $extension);
}

/**
 * Returns the file type specific function to create an empty image object.
 * 
 * @param string $extension The extension for which the suffix should be 
 *                          determined. There must be a mapping for the suffix 
 *                          in the function `getExtensionToFunctionMapping()`.
 * 
 * @return string der Funktionsname
 */
function getSaveFunctionNameForFileType($extension)
{
	return getFunctionNameForFileType('image', $extension);
}

/**
 * Creates an absolute file path from the passed filename and the working 
 * directory (@see `getOption()`).
 *
 * @param string $fileName the filename that should be appended to the current 
 *                         working directory.
 * 
 * @return string the absolute file path
 */
function getWallpaperPath($fileName)
{
	$path = getOption("d");
	
	if(substr($path, -1) !== "/")
	{
		$path .= "/";
	}
	
	return $path . $fileName;
}

/**
 * @return string[] the valid file extensions
 */
function getValidFileExtensions()
{
	return array_keys(getExtensionToFunctionMapping());
}

/**
 * returns an array of file names in the current working directory (@see 
 * `getOption()`). Optionally a filter function can be provided.
 *
 * @param function $filter An optional filter function. It gets a filename as 
 *                         as parameter and should return `true` if the file 
 *                         should be in the result set or `false` if the file 
 *                         should be filtered out. (@see `array_filter()`)
 * 
 * @return string[] array of filenames
 */
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

/**
 * prints a line of text to standard output.
 * 
 * @param string $text The text to be printed
 * @param array $args Optional array of arguments to be inserted in the output 
 *                    text (@see `sprintf()`).
 * @return void
 */
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

/**
 * prints an error message to standard output
 *
 * @param string $message The error message to be printed
 * @param int $line Optional line number is appended to the message in format
 *                  "ERROR: $message in line $line"
 * 
 * @return void
 */
function printerr($message, $line = -1)
{
	println("ERROR: %s %s", $message, ($line > -1) ? "in line " . $line : "");
}

/**
 * prints a debug message to standard output
 * 
 * @param string $message Optional debug message to be printed
 *
 * @return void
 */
function debug($message = "")
{
	$args = func_get_args();
	
	$args[0] = "DEBUG: " . (count($args) == 0 ? "" : $args[0]);
	
	call_user_func_array('println', $args);
}

/**
 * prints a list to standard output where each item is prepended with a numeric 
 * ID. The user is then prompted to enter the ID of their choice. If the 
 * provided list is empty an error message is printed. If the user enters an 
 * invalid ID (e.g. negative number, non-number characters, ...) an information 
 * is printed, the list is printed again and the user is prompted to enter an 
 * ID again.
 * 
 * @param string[] $list The list of choices which the user can choose from
 * @param string $message Optional message text to be printed before the list.
 *                        Default message is 'There are several choices: '.
 * 
 * @return string the user's choice
 */
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

/**
 * Calculates the total needed canvas size from the screen info objects
 *
 * @return int[] an array with the total needed width and height
 */
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

/**
 * Wraps the global canvas object for the stitched result wallpaper
 * 
 * @return object the canvas
 */
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

/**
 * Ouch... Need to read that code in more detail. PHPDoc will follow, I hope...
 */
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

/**
 * saves the stitched wallpaper. For the output filename @see `getOption()`.
 * 
 * @return void
 */
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

// The main doing. Needs more comments, I know...
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
