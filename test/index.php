<?php

define('phpless_start_time',microtime());

//error_reporting(E_ALL | E_STRICT);
//error_reporting(E_ALL);
error_reporting(E_ALL ^ E_STRICT);
ini_set('display_errors',1);


//get parser
$dir = dirname(__DIR__);

ParserTest::IncludeScripts( $dir.'/lib/Less' );


//get diff
require( $dir. '/test/php-diff/lib/Diff.php');
require( $dir. '/test/php-diff/lib/Diff/Renderer/Html/SideBySide.php');
require( $dir. '/test/php-diff/lib/Diff/Renderer/Html/Inline.php');


class ParserTest{

	//options
	var $compress = false;
	var $dir;
	var $test_dirs = array('less.js','bootstrap3','bootstrap-2.0.2');
	var $cache_dir;
	var $head;
	var $files_tested = 0;
	var $matched_count = 0;

	function __construct(){

		$this->cache_dir = __DIR__.'/_cache';

		if( !file_exists($this->cache_dir) || !is_dir($this->cache_dir) ){
			echo '<p>Invalid cache directory</p>';
		}elseif( !is_writable($this->cache_dir) ){
			echo '<p>Cache directory not writable</p>';
		}


		//get any other possible test folders
		$fixtures_dir = rtrim(__DIR__,'/').'/Fixtures';
		$temp = scandir($fixtures_dir);
		foreach($temp as $dir){
			if( $dir == '.' || $dir == '..' ){
				continue;
			}
			$full_path = $fixtures_dir.'/'.$dir.'/less';
			if( !file_exists($full_path) || !is_dir($full_path) ){
				continue;
			}
			$this->test_dirs[] = $dir;
		}
		$this->test_dirs = array_unique($this->test_dirs);


		//Set the directory to test
		if( !empty($_REQUEST['dir']) && in_array($_REQUEST['dir'],$this->test_dirs) ){
			$this->dir = $_REQUEST['dir'];
		}else{
			$this->dir = reset($this->test_dirs);
		}
		$dir = $fixtures_dir.'/'.$this->dir;

		$this->lessJsProvider($dir);
	}

    public function lessJsProvider($dir){

		if( isset($_GET['file']) ){
			$less = '/less/'.$_GET['file'].'.less';
			$css = '/css/'.$_GET['file'].'.css';
			$pairs = array( array($less,$css) );

		}else{

			$list = scandir($dir.'/css');
			foreach($list as $file){
				if( strpos($file,'.css') === false ){
					continue;
				}
				$pairs[] = array('/less/'.str_replace('.css','.less',$file), '/css/'.$file  );
			}

		}

		foreach($pairs as $files){
			$this->testLessJsCssGeneration( $dir, $files[0], $files[1] );
		}

    }

    public function testLessJsCssGeneration($dir, $less, $css){

		$this->files_tested++;
		$basename = basename($less);
		$basename = substr($basename,0,-5); //remove .less extension

		$less = $dir.$less;
		$css = $dir.$css;

		echo '<br/><a href="?dir='.rawurlencode($this->dir).'&amp;file='.rawurlencode($basename).'">'.$basename.'</a>';

		if( !file_exists($less) ){
			echo '<p>LESS file missing: '.$less.'</p>';
			return false;
		}elseif( !file_exists($css) ){
			echo '<p>CSS file missing: '.$css.'</p>';
			return false;
		}


		$options = array();
		if( $this->compress ){
			$options = array( 'compress'=>true );
		}


		$compiled = '';
		try{

			/**
			 * Less_Cache Testing
			Less_Cache::$cache_dir = $this->cache_dir;
			$cached_css_file = Less_Cache::Get( array($less=>'') );
			$compiled = file_get_contents( $this->cache_dir.'/'.$cached_css_file );
			*/

			$parser = new Less_Parser( $options );
			$parser->SetCacheDir( $this->cache_dir );
			$parser->parseFile($less);
			$compiled = $parser->getCss();

		}catch(\Exception $e){
			echo '<h1>Parser Error</h1>';
			echo '<p>'.$e->getMessage().'</p>';
		}

		$css = file_get_contents($css);

		if( empty($compiled) && empty($css) ){
			echo '<b>----empty----</b>';
			return;
		}


		// If compress is enabled, add some whitespaces back for comparison
		if( $this->compress ){
			$compiled = str_replace('{'," {\n",$compiled);
			//$compiled = str_replace('}',"\n}",$compiled);
			$compiled = str_replace(';',";\n",$compiled);
			$compiled = preg_replace('/\s*}\s*/',"\n}\n",$compiled);


			$css = preg_replace('/\n\s+/',"\n",$css);
			$css = preg_replace('/:\s+/',":",$css);
			$css = preg_replace('/;(\s)}/','$1}',$css);

		}

		$css = trim($css);
		$compiled = trim($compiled);



		if( $css === $compiled ){
			$this->matched_count++;
			echo ' (equals) ';

			if( !isset($_GET['file']) ){
				return;
			}

		}else{

			$compiled = explode("\n", $compiled );
			$css = explode("\n", $css );


			$options = array();
			$diff = new Diff($compiled, $css, $options);
			$renderer = new Diff_Renderer_Html_SideBySide();
			//$renderer = new Diff_Renderer_Html_Inline();
			echo $diff->Render($renderer);


			if( isset($_GET['file']) ){
				echo '</table>';
				echo '<table style="width:100%"><tr><td>';
				echo '<pre>';
				echo implode("\n",$compiled);
				echo '</pre>';
				echo '</td><td>';
				echo '<pre>';
				echo implode("\n",$css);
				echo '</pre>';
				echo '</td></tr></table>';
			}
		}


		$pos = strpos($less,'/less.php');

		if( isset($_GET['file']) ){
			$this->head .= '<link rel="stylesheet/less" type="text/css" href="'.substr($less,$pos).'" />';
		}
		//echo '<textarea>'.htmlspecialchars(file_get_contents($less)).'</textara>';

    }





	/**
	 * Include the necessary php files
	 *
	 */
	static function IncludeScripts( $dir ){

		$files = scandir($dir);

		usort($files,function($a,$b){
			return strlen($a)-strlen($b);
		});


		$dirs = array();
		foreach($files as $file){
			if( $file == '.' || $file == '..' ){
				continue;
			}

			$full_path = $dir.'/'.$file;
			if( is_dir($full_path) ){
				$dirs[] = $full_path;
				continue;
			}

			if( strpos($file,'.php') !== (strlen($file) - 4) ){
				continue;
			}

			include_once($full_path);
		}

		foreach($dirs as $dir){
			self::IncludeScripts( $dir );
		}

	}

	function Links(){

		echo '<ul id="links">';
		foreach($this->test_dirs as $dir){
			$class = '';
			if( $dir == $this->dir){
				$class = ' class="active"';
			}
			echo '<li '.$class.'><a href="?dir='.$dir.'">'.$dir.'</a></li>';
		}
		echo '</ul>';
	}

	function Summary(){

		if( !$this->files_tested ){
			return;
		}

		echo '<div id="summary">';

		//success rate
		echo '<fieldset><legend>Success Rate</legend>'.$this->matched_count.' out of '.$this->files_tested.'  files</fieldset>';

		//current memory usage
		$memory = memory_get_usage();
		echo '<fieldset><legend>Memory</legend>'.self::FormatBytes($memory).' ('.number_format($memory).')</fieldset>';

		//max memory usage
		$memory = memory_get_peak_usage();
		echo '<fieldset><legend>Memory Peak</legend>'.self::FormatBytes($memory).' ('.number_format($memory).')</fieldset>';

		//time
		echo '<fieldset><legend>Time (PHP):</legend>'.self::microtime_diff(phpless_start_time,microtime()).'</fieldset>';
		echo '<fieldset><legend>Time (Request)</legend>'.self::microtime_diff($_SERVER['REQUEST_TIME'],microtime()).'</fieldset>';

		echo '</div>';

	}


	function microtime_diff($a, $b = false, $eff = 6) {
		if( !$b ) $b = microtime();
		$a = array_sum(explode(" ", $a));
		$b = array_sum(explode(" ", $b));
		return sprintf('%0.'.$eff.'f', $b-$a);
	}

	static function FormatBytes($size, $precision = 2){
		$base = log($size) / log(1024);
		$suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
		$floor = max(0,floor($base));
		return round(pow(1024, $base - $floor), $precision) .' '. $suffixes[$floor];
	}
}

ob_start();
$test_obj = new ParserTest();
$content = ob_get_clean();

?>
<!DOCTYPE html>
<html><head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
	<title>Less.php Tests</title>
	<link rel="stylesheet" href="php-diff/styles.css" type="text/css" />
	<?php echo $test_obj->head ?>
	<style>
		html,body{padding:0;margin:0;}
		body{line-height:150%;font-size:13px;}
		a{color:#18BC9C;}

		fieldset{display:inline-block;border:0 none;font-size:13px;padding:2px;margin:5px 30px 5px 0;line-height:110%;width:auto;}
		legend{padding:0;margin:0;font-size:11px;color:rgba(255,255,255,0.5);}

		#heading *,
		#contents *{min-width:0;width:auto;}

		#heading{background:#2C3E50;color:#fff;padding:0 20px;height:70px;margin:0;}
		#heading *{color:#fff;}
		#heading h1{font-size:20px;line-height:70px;height:70px;margin:0;padding:0;}

		#links{float:right;margin:0;}
		#links li, #links a{display:inline-block;list-style:none;padding:0;height:70px;line-height:70px;}
		#links a{text-decoration:none;padding:0 20px;}

		#links .active a{background:#1A252F;}
		#links a:hover{color:#18BC9C;text-decoration:none;}


		#summary{background:#1A252F;padding:10px 20px;color:#fff;}

		#contents{padding:20px;}
	</style>
</head>
<body>

<?php

echo '<div id="heading">';
echo $test_obj->Links();
echo '<h1>Less.php Testing</h1>';
echo '</div>';

echo $test_obj->Summary();


if( isset($_GET['file']) ){
	echo '<script src="js/less-1.4.2.js" ></script>';
}

echo '<div id="contents">';
echo $content;
echo '</div>';

?>
</body></html>