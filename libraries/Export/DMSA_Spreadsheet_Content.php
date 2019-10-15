<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class DMSA_Spreadsheet_Content extends DMSA_DIObject {

	protected $__spreadsheet;
	protected $__datasource;
	protected $__cells;

	public static $FormatCodes = array(
		'commafy'		=>	'#,##0',
		'percentify'	=>	'0%'
	);

	public function __construct($schema, $spreadsheet, $scope){
		parent::__construct( $schema, $spreadsheet, $scope );
		$this->__cells = new stdClass;
		$this->__spreadsheet = $spreadsheet;
		$this->__datasource = property_exists($schema,'datasource')
			?	$schema->datasource
			:	null;
	}

	private static function ConvertBase($numberInput,$fromBaseInput,$toBaseInput){
		if($fromBaseInput==$toBaseInput){
			return $numberInput;
		}else{
			$fromBase = str_split($fromBaseInput,1);
			$toBase = str_split($toBaseInput,1);
			$number = str_split($numberInput,1);
			$fromLen=strlen($fromBaseInput);
			$toLen=strlen($toBaseInput);
			$numberLen=strlen($numberInput);
			$retval='';

			if ($toBaseInput == '0123456789'){
				$retval=0;
				for($i=1;$i<= $numberLen;$i++){
					$retval = bcadd($retval, bcmul(array_search($number[$i-1], $fromBase),bcpow($fromLen,$numberLen-$i)));
				}
				return $retval;
			}

			if($fromBaseInput != '0123456789'){
				$base10=convBase($numberInput, $fromBaseInput, '0123456789');
			}else{
				$base10 = $numberInput;
			}

			if($base10<strlen($toBaseInput)){
				return $toBase[$base10];
			}

			while($base10 != '0'){
				$retval = $toBase[bcmod($base10,$toLen)].$retval;
				$base10 = bcdiv($base10,$toLen,0);
			}
			return $retval;
		}
	}

	public static function CoordinatesToCell($x,$y){
		$alpha = self::ConvertBase($y,'0123456789','ABCDEFGHIJKLMNOPQRSTUVWXYZ');
		$x++;
		return "{$alpha}{$x}";
	}

	public static function CellToCoordinates($cell){
		list($alpha,$y) = preg_split('/(?<=[A-Z])(?=[0-9])/i',$cell,2);
		$x = self::ConvertBase($alpha,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','0123456789');
		$y--;
		return compact('x','y');
	}

	public static function CellToColumn($cell){
		list($alpha,$y) = preg_split('/(?<=[A-Z])(?=[0-9])/i',$cell,2);
		return $alpha;
	}

	public static function CellToRow($cell){
		return preg_replace('/[a-z]+/i','',$cell) * 1;
	}

	public static function CellsToRows($cells){
		$inputs = is_string($cells) ? explode(':',$cells) : $cells;
		$outputs = array();
		foreach($inputs as $input){
			$outputs[] = self::CellToRow($input);
		}
		return $outputs;
	}

	public static function CellsToColumns($cells){
		$inputs = is_string($cells) ? explode(':',$cells) : $cells;
		$outputs = array();
		foreach($inputs as $input){
			$outputs[] = self::CellToColumn($input);
		}
		return $outputs;
	}

	public static function CellToParts($cell){
		return preg_split('/(?<=[A-Z])(?=[0-9])/i',$cell,2);
	}

	public function get_datasource(){
		return is_null($this->__datasource)
			?	die('No datasource')
			:	(is_array($this->__datasource)
				?	$this->__datasource
				:	(is_object($this->__datasource)
					?	(property_exists($this->__datasource,'accessor')
						?	$this->resolve_generic_getter($this->__datasource)
						:	(property_exists($this->__datasource,'iterator')
							?	$this->resolve_iterative_property($this->__datasource->iterator)
							:	$this->__datasource))
					:	(is_string($this->__datasource)
						?	$this->resolve_di_datasource()
						:	$this->__datasource)));
	}

	protected function resolve_di_datasource(){
		$datasource = $this->resolve_di_string($this->__datasource);
		return $this->$datasource;
	}

	public function get_spreadsheet(){ return $this->__spreadsheet; }

	public function get_object_type(){ return 'generic'; }

	public function get_index(){ return $this->__spreadsheet->get_object_index($this); }

	private function unpack_style_selector_subsets( $input ){
		$split = explode('(',trim($input),2);
		$outer = trim(array_shift($split));
		$inner = !empty($split)
			?	explode('|',trim(substr(array_pop($split),0,-1)))
			:	array();
		return compact('outer','inner');
	}

	private function find_parenthesized_ranges(&$collector,$input,$idx=0){
		$len = strlen($input);
		$next = strpos($input,'(',$idx);
		if($next!==false){
			$idx = strpos($input,')',$next);
			$collector[$next] = $idx;
			return $this->find_parenthesized_ranges($collector,$input,$idx);
		}else return true;
	}

	protected function unpack_style_keys($input){
		$result = array();
		if(strpos($input,'(')!==false){
			$parenthesized = array();
			$this->find_parenthesized_ranges($parenthesized,$input);
			foreach($parenthesized as $from => $to){
				$tmp = substr($input,$from,$to);
				$input = substr_replace($input,str_replace(',','|',substr($input,$from,$to)),$from,$to);
			}
		}
		$sets = explode(',',$input);
		foreach($sets as $setString){
			extract($this->unpack_style_selector_subsets( $setString ));
			if(empty($inner)){
				$result[] = $outer;
			}else{
				foreach($inner as $innerToken){
					$result[] = "{$outer} {$innerToken}";
				}
			}
		}
		return $result;
	}

	protected function unpack_styles($spec){
		$styleSchema = array();
		foreach($spec as $key => $styles){
			$keys = $this->unpack_style_keys($key);
			foreach($keys as $styleKey){
				$styleSchema = array_replace_recursive($styleSchema,array($styleKey=>$this->resolve_styles( $styles )));
			}
			// if($key=='footer'){
			// 	exit(print_r(compact('spec','styleSchema'),true));
			// }
		}
		//self::CrashBlob($styleSchema);
		return $styleSchema;
	}

	protected function resolve_styles( $stylesObj ){
		$output = array();
		foreach($stylesObj as $prop => $val){
			$method = "resolve_{$prop}_style";
			$temp = method_exists($this,$method)
				?	$this->$method( $val )
				:	(array)$val;
			$output = array_merge_recursive($output,array($prop=>$temp));
		}
		return $output;
	}

	//---------------FONT---------------//
	protected function resolve_font_object( $input ){
		$props = array('name','bold','italic','superScript','subScript','underline','strike','size','color');
		$aliases = array('rgb'=>'color','rgba'=>'color','sub'=>'subScript','subscript'=>'subScript','super'=>'superScript','superscript'=>'superScript');
		$output = array();
		foreach($input as $prop => $val){
			$propName = in_array($prop,$props)
				?	$prop
				:	(array_key_exists($prop,$aliases)
					?	$aliases[$prop]
					:	false);
			if($propName!==false){
				$output[$propName] = $propName=='color'
					? $this->resolve_color($val)
					: $val;
			}
		}
		return $output;
	}

	protected function resolve_font_array( $input ){
		$bools = array('bold'=>'bold','italic'=>'italic','superScript'=>'superscript','subScript'=>'subscript','underline'=>'underline','strike'=>'strike');
		$output = array();
		foreach($input as $token){
			list($prop,$value)=in_array(strtolower($token),$bools)
				?	array($output[array_search(strtolower($token),$bools)],true)
				:	(substr($token,0,1)==='#'||substr($token,0,3)==='rgb'
					?	array('color',$this->resolve_color($token))
					:	(is_numeric($token)
						?	array('size',(float)($token*1))
						:	array('name',$token)));
			$output[$prop]=$value;
		}
		return $output;
	}

	protected function resolve_font_style( $spec ){
		return is_object($spec)
			?	$this->resolve_font_object($spec)
			:	$this->resolve_font_array(!is_array($spec)
				? explode(' ',(string)$spec)
				: $spec);
	}

	//---------------BORDERS---------------//
	protected function resolve_borders_style( $spec ){
		$sides = array('top','right','bottom','left');
		$output = array();
		foreach($spec as $side => $inputDetails){
			$use_side = $side=='outline' ? 'allborders' : $side;
			$temp = $this->resolve_borders_substyle($use_side,$inputDetails);
			$output = array_replace_recursive($output,$temp);
		}
		return $output;
	}

	protected function resolve_borders_substyle( $side, $spec ){
		$styles = array('none','thin','thick','dashDot','dashDotDot','dashed','dotted','double','hair','medium','mediumDashDot','mediumDashDotDot','mediumDashed','slantDashDot');
		$output = array();
		if(is_object($spec)){
			foreach($spec as $key => $value){
				if($key=='style' && in_array($value,$styles)){
					$output['style'] = $value;
				}elseif($key=='color'){
					$output['color'] = $this->resolve_color($value);
				}
			}
		}elseif(is_string($spec)){
			$tokens = explode(' ',$spec);
			foreach($tokens as $token){
				if(in_array($token,$styles)){
					$output['style'] = $token;
				}else{
					$output['color'] = $this->resolve_color($token);
				}
			}
		}
		// if(!array_key_exists('color',$output)){
		// 	$output['color'] = $this->resolve_color('#FFFFFF');
		// }
		return array($side => $output);
	}

	//---------------FILL---------------//
	protected function resolve_fill_object( $spec ){
		$output = array();
		foreach($spec as $prop => $val){
			switch($prop)
			{
				case 'startcolor':
				case 'endcolor':
				case 'color':
					$output['color'] = $this->resolve_color($val);
				break;

				case 'type':
				case 'rotation':
					$output[$prop] = $val;
				break;
			}
		}

		if(!array_key_exists('type',$output)){
			$output['type']='solid';
		}

		return $output;
	}

	protected function resolve_fill_style( $spec ){
		$style = is_string($spec)
			?	array('type'=>'solid',
				'color'=>$this->resolve_color($spec))
			:	(property_exists($spec,'rgb')
				?	array('type'=>'solid',
					'color'=>$this->resolve_color($spec->rgb))
				:	$this->resolve_fill_object($spec));

		return $style;
	}

	protected function resolve_color( $input ){
		$color = new DMSA_Color( $input );
		$hex = $color->hex_string();
		$argb = 'FF'.substr(strtoupper($hex),-6);
		return compact('argb');
	}

	protected function resolve_color_to_rgb( $input ){
		$color = new DMSA_Color( $input );
		$hex = $color->hex_string();
		$rgb = substr($hex,-6);
		return compact('rgb');
	}

	protected function apply_cell_styles($sheet,$cell,$styles){
		foreach($styles as $prop => $spec){
			$method = "apply_{$prop}_style";
			if(method_exists($this,$method)){
				$this->$method($sheet,$cell,$spec);
			}
		}
	}

}
