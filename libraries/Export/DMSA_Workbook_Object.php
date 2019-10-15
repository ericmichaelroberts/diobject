<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

abstract class DMSA_Workbook_Object extends DMSA {

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

	public static function CellsToColumns($cells){
		$inputs = explode(':',$cells);
		$outputs = array();
		foreach($inputs as $input){
			$outputs[] = self::CellToColumn($input);
		}
		return $outputs;
	}

	public function __get($prop){
		$method = "get_{$prop}";
		return method_exists($this,$method)
			?	$this->$method()
			:	$this->__spreadsheet->$prop;
	}
}
