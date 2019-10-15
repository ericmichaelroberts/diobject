<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class DMSA_Breakdown_Table extends DMSA_Basic_Table {

	public $type = 'breakdown';
	public $rowIndex = array();

	protected function consolidate_column_data(){
		foreach($this->columnIndex as $column_id => $column){
			if(sizeof($column->row_values)){
				if( sizeof($column->type) ){
					$unique_types = array_unique($column->type);
					if(sizeof($unique_types)===1){
						$column->type = array_shift($unique_types);
					}
				}
				if( sizeof($column->format) ){
					$unique_formats = array_unique($column->format);
					if(sizeof($unique_formats)===1){
						$column->format = array_shift($unique_formats);
					}
				}
			}
		}
	}

	protected function build_data_row( $row, $row_schema, $row_idx, $context=array() ){
		$height = property_exists($row_schema,'height') ? $row_schema->height : 12.75;
		if(property_exists($row_schema,'height')){
			$this->row_heights[$row_idx] = $height;
		}
		$temp = array(
			'@type' => 'data',
			'@height'=> array_key_exists($row_idx,$this->row_heights) ? $this->row_heights[$row_idx] : 12.75//property_exists($row_schema,'height') ? $row_schema->height : 12.75
		);
		foreach($this->columnIndex as $column_id => $column_obj){
			$context = array_merge(compact('row','row_index','column_id'),$context);
			if( property_exists($row_schema->columns,$column_id) ){
				$col_schema = $row_schema->columns->$column_id;

				$value = $this->resolve_getterable_prop($col_schema,'value',null,$context);
				$type = $this->resolve_getterable_prop($col_schema,'type','string',$context);
				$format = $this->resolve_getterable_prop($col_schema,'format',null,$context);

				$this->columnIndex[$column_id]->row_values[$row_idx] = $value;
				$this->columnIndex[$column_id]->type[$row_idx] = $type;

				if(!is_null($format)){
					$this->columnIndex[$column_id]->format[$row_idx] = $format;
				}

				$temp[$column_id] = $value;
			}else{
				$temp[$column_id] = null;
			}
		}

		$this->rowIndex[] = $temp;
	}

	protected function build_divider_row( $row, $row_schema, $row_idx, $context=array() ){
		$height = property_exists($row_schema,'height') ? $row_schema->height : 12.75;
		if(property_exists($row_schema,'height')){
			$this->row_heights[$row_idx] = $height;
		}
		$context = array_merge(compact('row','row_index'),$context);
		$temp = property_exists($row_schema,'label')
			?	array('@type'=>'divider','@label'=>$this->resolve_getterable_prop($row_schema,'label',null,$context))
			:	array('@type'=>'divider');
		$temp['@height'] = array_key_exists($row_idx,$this->row_heights) ? $this->row_heights[$row_idx] : 12.75;
		$this->rowIndex[] = $temp;
	}

	protected function build_headers_row( $row, $row_schema, $row_idx, $context=array() ){
		$thead = $this->thead;
		return $this->rowIndex[] = array('@type'=>'headers');
	}

	public function build_table_row_data( $schema ){
		$this->row_map = array_keys($this->columnIndex);
		$total_rows = sizeof($schema->rows);
		$data = $this->get_datasource();

		//Initialize Column Value/Type/Format containers...
		foreach($this->columnIndex as $column_id => $column_obj){
			$column_obj->row_values = array();
			$column_obj->type = array();
			$column_obj->format = array();
		}

		if(is_object($schema->rows) && property_exists($schema->rows,'iterator')){
			$iterator = $schema->rows->iterator;
			$iteratorProp = $iterator->each;
			$indexProp = property_exists($iterator,'index') ? $iterator->index : 'current_index';
			$valueProp = property_exists($iterator,'value') ? $iterator->value : 'current_row';
			$subSchema = $iterator->schema;

			foreach($this->$iteratorProp as $iterator_index => $iterator_value){
				//$data = $iterator_value;
				$outer_context = array(
					$indexProp => $iterator_index,
					$valueProp => $iterator_value
				);

				foreach($subSchema as $row_idx => $row_schema){
					$inner_context = array(
						'schema_index' => $row_idx,
						'row_index' => sizeof($this->rowIndex)
					);
					$row_idx = sizeof($this->rowIndex);
					$row_type = property_exists($row_schema,'type')
						?	$row_schema->type
						:	(property_exists($row_schema,'columns')
							?	'data'
							:	'divider');
					$context = array_merge($outer_context,$inner_context);

					$method = "build_{$row_type}_row";

					$this->$method( $data, $row_schema, $row_idx, $context );

					// $row_type==='data'
					// 	?	$this->build_data_row( $data, $row_schema, $row_idx, $context )
					// 	:	$this->build_divider_row( $data, $row_schema, $row_idx, $context );
				}


			}
		}else{
			foreach($schema->rows as $row_idx => $row_schema){
				$row_type = property_exists($row_schema,'type')
					?	$row_schema->type
					:	(property_exists($row_schema,'columns')
						?	'data'
						:	'divider');

				$method = "build_{$row_type}_row";

				$this->$method( $data, $row_schema, $row_idx, array(
					'schema_index' => $row_idx,
					'row_index' => sizeof($this->rowIndex)
				));
				// $row_type==='data'
				// 	?	$this->build_data_row( $data, $row_schema, $row_idx )
				// 	:	$this->build_divider_row( $data, $row_schema, $row_idx );
			}
		}

		$this->consolidate_column_data();
	}

	public function build_table_body( $schema ){
		$datasource = $this->rowIndex;
		$this->tbody = new stdClass;
		$this->tbody->rows = array();
		$this->tbody->total_rows = sizeof($datasource);
		$metaArgs = array('type','label','height');

		foreach($datasource as $row_idx => $row_data){
			$row = array();

			foreach($this->columnIndex as $col_id => $col_def){
				$row[$col_id] = array_key_exists($row_idx,$col_def->row_values)
					?	$col_def->row_values[$row_idx]
					:	null;
			}

			foreach($metaArgs as $arg){
				if(array_key_exists("@{$arg}",$row_data)){
					$row["@{$arg}"]=$row_data["@{$arg}"];
				}
			}

			$this->tbody->rows[] = (object)$row;
		}
	}


}
