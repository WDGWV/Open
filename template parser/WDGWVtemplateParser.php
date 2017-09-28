<?php
/** WDGWV Template Parser */

namespace WDGWV\General;

/**
 * WDGWV Template Parser
 *
 * This is the WDGWV Template Parser class
 *
 * @version Version 2.0
 * @author Wesley de Groot / WDGWV
 * @copyright 2017 Wesley de Groot / WDGWV
 * @package WDGWV/General
 * @subpackage TemplateParser
 * @link http://www.wesleydegroot.nl © Wesley de Groot
 * @link https://www.wdgwv.com © WDGWV
 */
class templateParser {
	/**
	 * Version number
	 * @var string version The version number
	 */
	const version = "2.0";

	/**
	 * The configuration
	 * @global
	 * @access private
	 * @var string[] The configuration
	 * @since Version 1.0
	 */
	private $config;

	/**
	 * The Parameters
	 *
	 * @global
	 * @access private
	 * @since Version 1.0
	 * @var string[] parameters[array]
	 */
	private $parameters;

	/**
	 * Temporary Parameters
	 *
	 * @global
	 * @access private
	 * @since Version 1.0
	 * @var string[] _parameters[array]
	 */
	private $_parameters;

	/**
	 * The unique identifier
	 *
	 * @global
	 * @access private
	 * @since Version 1.0
	 * @var int Unique identifier
	 */
	private $uniid;

	/**
	 * Construct the class
	 * @param string $templateDirectory The template directory
	 * @param string $minify Minify the output
	 * @param string $CDN If you use a CDN put the full url to the files here.
	 * @since Version 2.0 (Improved)
	 */
	public function __construct($templateDirectory = "./data/template/", $minify = true, $CDN = null) {
		$this->ready = false;
		$this->config = array();
		$this->config['CDN'] = $CDN; // By default Content Delivery Network = off.
		$this->config['templateDirectory'] = $templateDirectory;
		$this->config['external'] = !class_exists("WDGWV") ? true : false;
		$this->config['hidecomments'] = $minify;
		$this->config['minify'] = $minify;
		$this->parameters = array();
	}

	/**
	 * Desctruct the class
	 * @since Version 1.0
	 * @internal
	 */
	public function __destruct() {

	}

	/**
	 * Set the template.
	 *
	 * @param string $templateFile The template directory
	 * @param string $TemplateFileExtension The extension
	 * @access public
	 * @since Version 2.0 (Improved)
	 */
	public function setTemplate($templateFile = 'default', $TemplateFileExtension = 'tpl', $fileURL = "/assets/") {
		if (file_exists(
			$f = $this->config['templateDirectory'] . $templateFile . "/theme." . $TemplateFileExtension
		)) {
			$this->config['theme'] = $templateFile;
			$this->config['themeExtension'] = $TemplateFileExtension;
			$this->config['templateFiles'] = $fileURL;
			$this->ready = true;
		} else {
			$this->fatalError('The template file ' . $f . ' does not exists');
			$this->ready = false;
		}
	}

	/**
	 * Set parameter config.
	 *
	 * @param string $parameterStart The starting parameter
	 * @param string $parameterEnd The ending parameter
	 * @access public
	 * @since Version 2.0
	 */
	public function setParameter($parameterStart = "\{WDGWV:", $parameterEnd = "\}") {
		$this->config['parameter'] = array($parameterStart, $parameterEnd);
	}

	/**
	 * Set parameter config.
	 *
	 * @param string $parameterStart The starting parameter
	 * @param string $parameterEnd The ending parameter
	 * @deprecated 2.0
	 * @access public
	 * @since Version 1.0
	 */
	public function setParameterStart($parameterStart = "\{WDGWV:", $parameterEnd = "\}") {
		$this->config['parameter'] = array($parameterStart, $parameterEnd);
	}

	/**
	 * Bind a parameter.
	 *
	 * @param string $parameter What parameter to replace
	 * @param string $replaceWith Replace with this
	 * @access public
	 * @since Version 2.0
	 */
	public function bindParameter($parameter, $replaceWith) {
		$this->parameters[] = array($parameter, $replaceWith);
	}

	/**
	 * Parses the template.
	 *
	 * @since Version 2.0 (Improved)
	 * @access private
	 * @param string $data Optional data to parse, default null
	 * @param string[] $withParameters Optional parameters to parse (array), default null
	 */
	private function _parse($data = null, $withParameters = null) {
		$this->uniid = $uniid = uniqid();

		if (!$this->ready) {
			return;
		}

		if (!isset($this->config['theme'])) {
			$this->config['theme'] = 'default';
		}

		if (!in_array('TEMPLATE_DIR', $this->parameters)) {
			$this->parameters[] = array(
				'TEMPLATE_DIR',
				sprintf('%s', $this->config['templateFiles']),
			);
		}

		$template = ($data === null) ? file_get_contents(
			sprintf(
				'%s%s/theme.%s',
				$this->config['templateDirectory'],
				$this->config['theme'],
				$this->config['themeExtension']
			)
		) : $data;

		$template = preg_replace(
			'/\{if (.*)\}/',
			'<?php if (\\1) { ?>',
			$template
		);

		$template = preg_replace(
			'/\{else\}/',
			'<?php }else{ ?>',
			$template
		);

		$template = preg_replace(
			'/\{\/if\}/',
			'<?php } ?>',
			$template
		);

		$template = preg_replace(
			'/\{endif\}/',
			'<?php } ?>',
			$template
		);

		$template = preg_replace(
			'/\{elseif (.*)\}/',
			'<?php } elseif (\\1) { ?>',
			$template
		);

		$template = preg_replace_callback(
			'/\{for (\w+)\}(.*)\{\/for\}/s',
			array($this, '__parseArray'),
			$template
		);

		$template = preg_replace_callback(
			'/\{while (\w+)\}(.*)\{\/(while|wend)\}/s',
			array($this, '__parseWhile'),
			$template
		);

		$template = preg_replace_callback(
			'/\{TEMPLATE LOAD:\'(.*)\' CONFIG:\'(.*)\'\}/',
			array($this, '__parse'),
			$template
		);

		$template = preg_replace_callback(
			"/\{TEMPLATE LOAD:'(.*)'\}/",
			array($this, '__parse'),
			$template
		);

		$template = preg_replace(
			'/\{PHP (.*)\}/', //Dangerous, do not use if you don't know what you are doing
			'<?php \\1 ?>',
			$template
		);

		$template = preg_replace(
			'/\{PHP\}(.*)\{\/PHP\}/s', //Dangerous, do not use if you don't know what you are doing
			'<?php \\1 ?>',
			$template
		);

		$template = preg_replace(
			'/\{#(.*?)#\}/',
			'<?php if(function_exists(\'translate\')) { echo translate(\'\\1\'); }else{ echo \'\\1\'; } ?>',
			$template
		);

		// script src="./" support
		if ($this->config['CDN'] === null) {
			$template = preg_replace(
				'/<script(.*)src=("|\')\.\//',
				'<script\\1src=\\2' . $this->config['templateFiles'],
				$template
			);
		} else {
			$template = preg_replace(
				'/<script(.*)src=("|\')\.\//',
				'<script\\1src=\\2' . $this->config['CDN'],
				$template
			);
		}

		// link href="./" support
		if ($this->config['CDN'] === null) {
			$template = preg_replace(
				'/<link(.*)href=("|\')\.\//',
				'<link\\1href=\\2' . $this->config['templateFiles'],
				$template
			);
		} else {
			$template = preg_replace(
				'/<link(.*)href=("|\')\.\//',
				'<link\\1href=\\2' . $this->config['CDN'],
				$template
			);
		}

		if ($withParameters === null) {
			for ($i = 0; $i < sizeof($this->parameters); $i++) {
				if (!is_array($this->parameters[$i][1])) {
					$template = preg_replace(
						'/' .
						$this->config['parameter'][0] .
						$this->parameters[$i][0] .
						$this->config['parameter'][1] .
						'/',

						$this->parameters[$i][1],

						$template
					);
				}
			}
		} else {
			for ($i = 0; $i < sizeof($withParameters); $i++) {
				if (!is_array($withParameters[$i][1])) {
					$template = preg_replace(
						'/' .
						$this->config['parameter'][0] .
						$withParameters[$i][0] .
						$this->config['parameter'][1] .
						'/',

						$withParameters[$i][1],

						$template
					);
				}
			}
		}

		if (is_writable('.')) {
			$fh = @fopen('tmp_' . $uniid . '.bin', 'w');
			@fwrite($fh, $template);
			@fclose($fh);
		}

		if (!file_exists('tmp_' . $uniid . '.bin')) {
			@ob_start();
			$ob = @eval(sprintf('%s%s%s%s%s', '/* ! */', ' ?>', $template, '<?php ', '/* ! */'));
			$ob = ob_get_contents();
			@ob_end_clean();

			@unlink('tmp_' . $uniid . '.bin');
			if (!$ob) {
				$this->fatalError('Failed to parse the template.');
			} else {
				return $this->minify($ob);
			}
		} else {
			@ob_start();
			$ob = include 'tmp_' . $uniid . '.bin';
			$ob = ob_get_contents();
			@ob_end_clean();

			@unlink('tmp_' . $uniid . '.bin');
			if (!$ob) {
				$this->fatalError('Failed to parse the template.');
			} else {
				return $this->minify($ob);
			}
		}
	}

	/**
	 * Parse a {while} loop in the template.
	 *
	 * @since Version 2.0
	 * @access private
	 * @param string[] $d Data/template to parse
	 * @internal
	 */
	public function __parseWhile($d) {
		$returning = '';
		$this->_parameters = array();

		for ($i = 0; $i < sizeof($this->parameters); $i++) {
			if ($this->parameters[$i][0] == $d[1]) {
				if (is_array($this->parameters[$i][1])) {
					// Ok. here's the fun part.
					$_templateData = '';
					$_found = 0;
					$_keys = array();

					for ($z = 0; $z < sizeof($this->parameters[$i][1]); $z++) {
						// .. parse with {$this->parameters[$i][1][$z]} as parameters
						$_temp = $d[2];
						foreach ($this->parameters[$i][1][$z] as $key => $value) {
							$_temp = preg_replace(
								$a = "/{$d[1]}\.{$key}/",
								$b = $value,
								$_temp
							);

							if (preg_match($a, $d[2])) {
								$_found++;
							} else {
								$_keys[] = "{$d[1]}.{$key}";
							}
						}
						$returning .= $this->_parse($_temp);

						if ($_found == 0) {
							$this->fatalError(sprintf('%s%s%s%s</b>&nbsp;',
								'Missing a replacement key in a while-loop!<br />',
								'While loop: <b>{$d[1]}</b><br />',
								'Confirm existence for least one of the following keys: <b>',
								implode(',', $_keys)
							));
						}

					}
					return $returning;
				}
			}
		}
	}

	/**
	 * Parse a {for} loop in the template.
	 *
	 * @since Version 2.0
	 * @access private
	 * @param string[] $d Data/template to parse
	 * @internal
	 */
	public function __parseArray($d) {
		$returning = '';

		for ($i = 0; $i < sizeof($this->_parameters); $i++) {
			if ($this->_parameters[$i][0] == $d[1]) {
				$this->_parameters[$i][1] = preg_replace('/;/', ',', $this->_parameters[$i][1]);
				$explode = explode(",", $this->_parameters[$i][1]);
				for ($z = 0; $z < sizeof($explode); $z++) {
					$_t = $d[2];
					$_t = preg_replace("/\{{$d[1]}\}/", $explode[$z], $_t);

					$returning .= $_t;
				}
			}
		}

		return $returning;
	}

	/**
	 * Parse a sub-template.
	 *
	 * @since Version 2.0
	 * @access private
	 * @param string[] $d Data/template to parse
	 * @internal
	 */
	public function __parse($d) {
		if (isset($d[2])) {
			$this->_parameters = array();

			$cfg = explode(';', $d[2]);
			for ($i = 0; $i < sizeof($cfg); $i++) {
				$_d = explode("=", $cfg[$i]);
				$this->_parameters[] = array($_d[0], $_d[1]);
			}
		}

		return $this->_parse(
			file_get_contents($this->config['templateDirectory'] . $this->config['theme'] . '/' . $d[1]),
			$this->_parameters
		);
	}

	/**
	 * Minify a page output
	 *
	 * @since Version 2.0
	 * @access private
	 * @param string $contents The contents to minify
	 */
	private function minify($contents) {
		$search = array(
			'/\>[^\S ]+/s', // strip whitespaces after tags, except space
			'/[^\S ]+\</s', // strip whitespaces before tags, except space
			'/<!--(.|\s)*?-->/', // Remove HTML comments
			'#\btrue\b#', // Replace `true` with `!0` and `false` with `!1` [^3]
			'#\bfalse\b#', // Replace `true` with `!0` and `false` with `!1` [^3]
			'/[^:]\/\/.*/', // Remove JS comments
			'~//<!\[CDATA\[\s*|\s*//\]\]>~', // Remove JS comments
			'/\s\s+/', // remove whitespaces
			'/\)if/', // fix javascript error
		);

		$replace = array(
			'>',
			'<',
			'',
			'!0',
			'!1',
			'',
			'',
			'',
			');if',
		);
		$contents = preg_replace($search, $replace, $contents);

		return $contents;
	}

	/**
	 * Display.
	 *
	 * @access public
	 * @since Version 1.0
	 */
	public function display() {
		if (!isset($this->config['parameter'])) {
			$this->setParameter();
		}

		echo $this->_parse();
	}

	/**
	 * Parses a fatal error.
	 *
	 * @access private
	 * @param string $errorDescription The error description
	 * @param string $errorFile The filename
	 * @param string $errorLine The linenumber in the file
	 * @param string $helpURL If available the URL
	 * @since Version 2.0
	 */
	private function fatalError($errorDescription, $errorFile = __FILE__, $errorLine = __LINE__, $helpURL = null) {
		if (file_exists($f = './data/template/default/modal.js')) {
			echo sprintf('<script>%s</script>', file_get_contents($f));
			echo sprintf('<script>openPopup(\'Fatal Error\', \'%s\', \'hidden\', function(){window.location.reload();}, \'hidden\', \'Reload\', \'WDGWV Template Parser\');</script>', $errorDescription);
			exit;
		} else {
			exit("Fatal Eroor: {$errorDescription}");
		}
	}

	// debug_backtrace()
}

/*
{TEMPLATE LOAD:'post.html' CONFIG:'TITLE=321;CONTENT=Pzerty;RMLink=/rm/1;KEYWORDS=tag,post,Else;DATE=Today;COMMENTS=2;SHARES=8;'}
{while post}
{TEMPLATE LOAD:'post.html' CONFIG:'TITLE=post.title;CONTENT=post.content;RMLink=post.rmLink;KEYWORDS=post.keywords;DATE=post.date;COMMENTS=post.comments;SHARES=post.shares;'}
{/while}
 */

?>