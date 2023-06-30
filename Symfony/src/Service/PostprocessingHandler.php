<?php
/**
 * Created by JetBrains PhpStorm.
 * User: iluvatar
 * Date: 31/7/13
 * Time: 10:26 AM
 * To change this template use File | Settings | File Templates.
 */

namespace Codebender\CompilerBundle\Handler;


class PostprocessingHandler
{
	/**
	 * \brief Converts text with ANSI color codes to HTML.
	 *
	 * \param string $text The string to convert.
	 * \return A string with HTML tags.
	 *
	 * Takes a string with ANSI color codes and converts them to HTML tags. Can be
	 * useful for displaying the output of terminal commands on a web page. Handles
	 * codes that modify the color (foreground and background) as well as the format
	 * (bold, italics, underline and strikethrough). Other codes are ignored.
	 *
	 * An ANSI escape sequence begins with the characters <b>^[</b> (hex 0x1B) and
	 * <b>[</b>, and ends with <b>m</b>. The color code is placed in between. Multiple
	 * color codes can be included, separated by semicolon.
	 */
	function convertANSItoHTML($text)
	{
		$FORMAT = array(
			0 => NULL, // reset modes to default
			1 => "b", // bold
			3 => "i", // italics
			4 => "u", // underline
			9 => "del", // strikethrough
			30 => "black", // foreground colors
			31 => "red",
			32 => "green",
			33 => "yellow",
			34 => "blue",
			35 => "purple",
			36 => "cyan",
			37 => "white",
			40 => "black", // background colors
			41 => "red",
			42 => "green",
			43 => "yellow",
			44 => "blue",
			45 => "purple",
			46 => "cyan",
			47 => "white");
		// Matches ANSI escape sequences, starting with ^[[ and ending with m.
		// Valid characters inbetween are numbers and single semicolons. These
		// characters are stored in register 1.
		//
		// Examples: ^[[1;31m ^[[0m
		$REGEX = "/\x1B\[((?:\d+;?)*)m/";

		$text = htmlspecialchars($text);
		$stack = array();

		// ANSI escape sequences are located in the input text. Each color code
		// is replaced with the appropriate HTML tag. At the same time, the
		// corresponding closing tag is pushed on to the stack. When the reset
		// code '0' is found, it is replaced with all the closing tags in the
		// stack (LIFO order).
		while (preg_match($REGEX, $text, $matches))
		{
			$replacement = "";
			foreach (explode(";", $matches[1]) as $mode)
			{
				switch ($mode)
				{
					case 0:
						while ($stack)
							$replacement .= array_pop($stack);
						break;
					case 1:
					case 3:
					case 4:
					case 9:
						$replacement .= "<$FORMAT[$mode]>";
						array_push($stack, "</$FORMAT[$mode]>");
						break;
					case 30:
					case 31:
					case 32:
					case 33:
					case 34:
					case 35:
					case 36:
					case 37:
						$replacement .= "<font style=\"color: $FORMAT[$mode]\">";
						array_push($stack, "</font>");
						break;
					case 40:
					case 41:
					case 42:
					case 43:
					case 44:
					case 45:
					case 46:
					case 47:
						$replacement .= "<font style=\"background-color: $FORMAT[$mode]\">";
						array_push($stack, "</font>");
						break;
					default:
						error_log(__FUNCTION__."(): Unhandled ANSI code '$mode'");
						break;
				}
			}
			$text = preg_replace($REGEX, $replacement, $text, 1);
		}

		// Close any tags left in the stack, in case the input text didn't.
		while ($stack)
			$text .= array_pop($stack);

		return $text;
	}
}
