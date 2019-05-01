<?php
	// PHP short open tag finder.
	// (C) 2019 CubicleSoft.  All Rights Reserved.

	if (!isset($_SERVER["argc"]) || !$_SERVER["argc"])
	{
		echo "This file is intended to be run from the command-line.";

		exit();
	}

	// Temporary root.
	$rootpath = str_replace("\\", "/", dirname(__FILE__));

	require_once $rootpath . "/support/cli.php";
	require_once $rootpath . "/support/str_basics.php";

	// Process the command-line options.
	$options = array(
		"shortmap" => array(
			"?" => "help",
		),
		"rules" => array(
			"ask" => array("arg" => false),
			"ext" => array("arg" => true, "multiple" => true),
			"help" => array("arg" => false)
		)
	);
	$args = CLI::ParseCommandLine($options);

	if (isset($args["opts"]["help"]) || count($args["params"]) != 1)
	{
		echo "Short open tag finder for PHP\n";
		echo "Purpose:  Finds short open tag references in PHP files.\n";
		echo "          Works under PHP 8 w/ pull request #3975 applied (https://github.com/php/php-src/pull/3975).\n";
		echo "\n";
		echo "Syntax:  " . $args["file"] . " [options] directory\n";
		echo "Options:\n";
		echo "\t-ask   Ask about the references that are found and modify the file only if the suggested changes are accepted.\n";
		echo "\t-ext   Additional case-insensitive file extension to look for.  Default is 'php', 'php3', 'php4', 'php5', 'php7', and 'phtml'.\n";
		echo "\n";
		echo "Examples:\n";
		echo "\tphp " . $args["file"] . " /var/www\n";
		echo "\tphp " . $args["file"] . " -ext phpapp -ext html /var/www\n";
		echo "\tphp " . $args["file"] . " -ask /var/www\n";

		exit();
	}

	// Very large PHP files will run the tokenizer out of RAM.
	ini_set("memory_limit", "-1");

	$lastfile = "";
	function OutputSplitter($currfile)
	{
		global $lastfile;

		if ($lastfile !== $currfile)
		{
			echo "--------------------------------------------------\n";

			$lastfile = $currfile;
		}
	}

	// This function does the heavy lifting.
	function ScanPath($path)
	{
		global $args, $exts, $filesscanned, $linesscanned, $numrefs, $numreffiles, $filesmodified, $linesmodified;

		// Some OSes read directories in non-alphabetic order.  Reorder the list of files.
		$files = array();
		$dir = opendir($path);
		if ($dir)
		{
			while (($file = readdir($dir)) !== false)
			{
				if ($file !== "." && $file !== "..")
				{
					if (is_dir($path . "/" . $file) || isset($exts[strtolower(Str::ExtractFileExtension($file))]))  $files[] = $file;
				}
			}

			closedir($dir);
		}

		sort($files, SORT_NATURAL | SORT_FLAG_CASE);

		// Now process the files.
		foreach ($files as $file)
		{
			if (is_dir($path . "/" . $file))  ScanPath($path . "/" . $file);
			else
			{
				$ext = strtolower(Str::ExtractFileExtension($file));
				if (isset($exts[$ext]))
				{
					$exts[$ext]++;

					// Read in the file.
					$data = file_get_contents($path . "/" . $file);
					if (strlen($data) > 1000000)
					{
						OutputSplitter($path . "/" . $file);
						echo "File size warning while loading " . realpath($path . "/" . $file) . ":  " . number_format(strlen($data), 0) . " bytes\n\n";
					}
					$filesscanned++;

					// Modify the data for comparison later.
					$data2 = $data;
					do
					{
						$modified = false;

						// Be overly aggressive on RAM cleanup.
						if (function_exists("gc_mem_caches"))  gc_mem_caches();

						// Use the built-in PHP token parser.
						$tokens = token_get_all($data2);

						// On very large PHP files (> 1MB in size such as those generated by PHP Decomposer), RAM usage will skyrocket temporarily.
						if (memory_get_usage(true) > 100000000)
						{
							OutputSplitter($path . "/" . $file);
							echo "RAM usage warning while parsing " . realpath($path . "/" . $file) . ":  " . number_format(memory_get_usage(true), 0) . " bytes\n\n";
						}

						$data2 = "";
						foreach ($tokens as $tnum => $token)
						{
							// If short open tags are enabled, this re-parses the content as if they were disabled.
							if (!$modified && is_array($token) && $token[0] === T_OPEN_TAG && $token[1] === "<" . "?")
							{
								if (!isset($tokens[$tnum + 1]) || (is_array($tokens[$tnum + 1]) && strtolower(trim(substr($tokens[$tnum + 1][1], 0, 3))) !== "xml" && strtolower(trim(substr($tokens[$tnum + 1][1], 0, 3))) !== "mso"))
								{
									$token[1] .= "php";
									if (isset($tokens[$tnum + 1]) && ltrim($tokens[$tnum + 1][1]) === $tokens[$tnum + 1][1])  $token[1] .= " ";

									$numrefs++;
									$modified = true;
								}
							}

							// Standardizes on all lowercase 'php'.
							if (!$modified && is_array($token) && $token[0] === T_OPEN_TAG && rtrim(strtolower($token[1])) === "<" . "?php" && rtrim($token[1]) !== "<" . "?php")
							{
								$token[1] = "<" . "?php" . (string)substr($token[1], 5);

								$numrefs++;
								$modified = true;
							}

							// If short open tags are disabled, this finds the first entry and tries to parse it as an open tag.
							if (!$modified && is_array($token) && $token[0] === T_INLINE_HTML)
							{
								$tags = explode("<" . "?", $token[1]);
								if (count($tags) > 1)
								{
									$y = count($tags);
									for ($x = 1; $x < $y; $x++)
									{
										if (strtolower(trim(substr($tags[$x], 0, 3))) !== "xml" && strtolower(trim(substr($tags[$x], 0, 4))) !== "mso-")
										{
											$tags[$x] = "php" . ($tags[$x]{0} !== "\t" && $tags[$x]{0} !== " " && $tags[$x]{0} !== "\r" && $tags[$x]{0} !== "\n" ? " " : "") . $tags[$x];

											$token[1] = implode("<" . "?", $tags);

											$numrefs++;
											$modified = true;

											break;
										}
									}
								}
							}

							$data2 .= (is_array($token) ? $token[1] : $token);
						}

						$tokens = false;
					} while ($modified);

					// Compare the two data blobs.
					$lines = explode("\n", $data);
					$linesscanned += count($lines);
					$lines2 = explode("\n", $data2);
					$numproposed = 0;
					foreach ($lines as $num => $line)
					{
						if ($lines[$num] !== $lines2[$num])
						{
							OutputSplitter($path . "/" . $file);
							echo "Line " . ($num + 1) . " in " . realpath($path . "/" . $file) . ":\n";
							echo "    " . trim($line) . "\n";
							$numproposed++;

							if (isset($args["opts"]["ask"]))
							{
								echo "=>\n";
								echo "    " . ltrim($lines2[$num]) . "\n\n";
							}
						}
					}

					if ($numproposed)  $numreffiles++;

					// Ask the user if they want to replace the file with the proposed changes applied (if the option has been enabled).
					if (isset($args["opts"]["ask"]) && $numproposed)
					{
						$args2 = array("opts" => array(), "params" => array());

						$change = CLI::GetYesNoUserInputWithArgs($args2, false, ($numproposed == 1 ? "Accept change" : "Accept " . $numproposed . " changes"), "N");

						// Apply all of the proposed changes for the file.
						if ($change)
						{
							file_put_contents($path . "/" . $file, $data2);

							$filesmodified++;
							$linesmodified += $numproposed;
						}
					}
				}
			}
		}
	}

	// Path to scan.
	$path = realpath($args["params"][0]);

	if (! is_dir($path)) {
		exit("Sorry, the provided argument isn't a directory");
	}

	// What file extensions to look at (and keep tabs on the number of).
	$exts = array(
		"php" => 0,
		"php3" => 0,
		"php4" => 0,
		"php5" => 0,
		"php7" => 0,
		"phtml" => 0
	);

	if (!isset($args["opts"]["ext"]))  $args["opts"]["ext"] = array();
	foreach ($args["opts"]["ext"] as $ext)  $exts[strtolower(ltrim($ext, "."))] = 0;

	// Other stats.
	$filesscanned = 0;
	$linesscanned = 0;
	$numrefs = 0;
	$numreffiles = 0;
	$filesmodified = 0;
	$linesmodified = 0;
	$extsfound = array();

	// Start the scan.
	ScanPath($path);

	// Dump the statistics from the run.
	OutputSplitter("");
	echo "\n";
	echo "File extensions found in path (extension => # of instances):\n";
	arsort($exts);
	foreach ($exts as $ext => $num)
	{
		if ($num)  echo "    ." . $ext . " => " . $num . "\n";
	}

	echo "\n";
	echo "Total files scanned:  " . number_format($filesscanned, 0) . "\n";
	echo "Total lines scanned:  " . number_format($linesscanned, 0) . "\n";
	echo "Total short open tag references:  " . number_format($numrefs, 0) . "\n";
	echo "Total files w/ short open tag references:  " . number_format($numreffiles, 0) . "\n";
	if (isset($args["opts"]["ask"]))
	{
		echo "Modified files:  " . number_format($filesmodified, 0) . "\n";
		echo "Modified lines:  " . number_format($linesmodified, 0) . "\n";
	}
?>
