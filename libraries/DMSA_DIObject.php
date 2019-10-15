<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

abstract class DMSA_DIObject extends DMSA implements Serializable {

	protected $__fw;
	protected $__ui;
	protected $__scope;
	protected $__schema;
	protected $__methods;
	protected $__resolved;
	protected $__providers;
	protected $__accessors;
	protected $__container;

	const scope_name = 'context';

	private static function CreateFunction( $args, $body ){
		return eval("return function({$args}){ {$body} };");
	}

	public static function AnyPropertiesExist($object,$properties=array()){
		while(!empty($properties)){
			if(property_exists($object,array_shift($properties))){
				return true;
			}
		}
		return false;
	}

	public static function AllPropertiesExist($object,$properties=array()){
		while(!empty($properties)){
			if(!property_exists($object,array_shift($properties))){
				return false;
			}
		}
		return true;
	}

	protected static function GetClassLineage($instance){
		$class = get_class($instance);
		$output = array($class);
		while($class!=__CLASS__){
			$class=get_parent_class($class);
			$output[]=$class;
		}
		return $output;
	}

	protected static function ApplySerialization(&$state,$instance){
		$state['schema'] = $instance->__schema;
		$state['providers'] = $instance->__providers;
		$state['resolved'] = $instance->__resolved;
		$state['accessors'] = $instance->__accessors;
		$state['scope'] = $instance->__scope;
		$state['ui'] = $instance->__ui;
		return $state;
	}

	protected static function ApplyDeserialization(&$state,$instance){
		$instance->__methods = new stdClass;
		$instance->__fw =& get_instance();
		$instance->__schema = $state['schema'];
		$instance->__providers = $state['providers'];
		$instance->__resolved = $state['resolved'];
		$instance->__accessors = $state['accessors'];
		$instance->__scope = $state['scope'];
		$instance->__ui = $state['ui'];

		if(property_exists($instance->__schema,'methods')){
			$instance->init_methods($instance->__schema->methods);
		}

		return $state;
	}

	public function serialize(){
		$classChain = self::GetClassLineage($this);
		while(!empty($classChain)){
			$currentClass=array_pop($classChain);
			$currentClass::ApplySerialization($state,$this);
		}
		$state['stored_at'] = date('H:i:s');
		return serialize($state);
	}

	public function unserialize( $data ){
		$this->__fw =& get_instance();
		$state = unserialize($data);
		$classChain = self::GetClassLineage($this);
		while(!empty($classChain)){
			$currentClass=array_pop($classChain);
			$currentClass::ApplyDeserialization($state,$this);
		}
	}

	public function __construct( $schema=null, $container=null, $scope=null ){
		$this->__schema = $schema;
		$this->__container = $container;
		$this->__scope = (array)$scope;

		if($schema){
			if(property_exists($schema,'methods')){
				$this->init_methods($schema->methods);
			}

			if(property_exists($schema,'properties')){
				$this->init_properties($schema->properties);
			}
		}
	}

	public function __call($method,$args){
		$haveMethods = is_object($this->__methods);
		$haveContainer = is_object($this->__container);
		switch($method)
		{
			case 'debugger':
				return call_user_func_array(array($this,'debugger'),$args);
			break;

			case 'resolve_dependencies':
				return call_user_func_array(array($this,'resolve_dependencies'),$args);
			break;

			default: return $haveMethods
				?	(property_exists($this->__methods,$method)
					?	call_user_func_array($this->__methods->$method,$args)
					:	($haveContainer
						?	call_user_func_array(array($this->__container,$method),$args)
						:	$this->di_call_fail($method,$args)))
				:	($haveContainer
					?	call_user_func_array(array($this->__container,$method),$args)
					:	$this->di_call_fail($method,$args));
			break;
		}
	}

	public function __get($prop){
		$method = "get_{$prop}";
		$haveSchema = is_object($this->__schema);
		$haveResolved = is_object($this->__resolved);
		$haveSchemaProps = $haveSchema && property_exists($this->__schema,'properties');
		$haveSchemaProp = $haveSchemaProps && property_exists($this->__schema->properties,$prop);
		$haveContainer = is_object($this->__container);
		$haveScope = is_array($this->__scope);

		$value = property_exists($this,$prop)
			?	$this->$prop
			:	(method_exists($this,$method)
				?	$this->$method()
				:	($haveResolved && property_exists($this->__resolved,$prop)
					?	$this->__resolved->$prop
					:	($haveSchemaProps && property_exists($this->__schema->properties,$prop)
						?	$this->get_di_property( $prop, $this->__schema->properties->$prop )
						:	($haveScope
							?	(array_key_exists($prop,$this->__scope)
								?	$this->__scope[$prop]
								:	($haveContainer
									?	$this->__container->$prop
									:	$this->di_get_fail($prop)))
							:	($haveContainer
								?	$this->__container->$prop
								:	$this->di_get_fail($prop))))));

		if( $haveResolved
			&& $haveSchemaProp
			&& is_object($this->__schema->properties->$prop)
			&& property_exists($this->__schema->properties->$prop,'cached')){
				$this->__resolved->$prop = $value;
		}

		return $value;
	}

	public function __set( $prop, $value ){
		return $this->set_and_resolve( $prop, $value );
		if($this->cached_value_exists($prop)){
			$this->__resolved->$prop = $value;
		}

		if($this->prop_defined($prop)){
			$deps = $this->get_dependents($prop);
			$this->uncache_dependency_values( $deps );
		}
	}

	public function __unset($prop){
		if($this->cached_value_exists($prop)){
			unset($this->__resolved->$prop);
			if($this->prop_defined($prop)){
				$deps = $this->get_dependents($prop);
				foreach($deps as $dep){
					unset($this->$dep);
				}
			}
		}
	}

	protected function set_and_resolve( $prop, $value=null ){
		$this->__resolved->$prop = $value;
		if($this->prop_defined($prop)){
			$deps = $this->get_dependents($prop);
			$this->uncache_dependency_values( $deps );
		}
	}

	protected function uncache_dependency_values( $deps ){
		$flush = array_values($deps);
		foreach($deps as $dep){
			$this->collect_dependency_cascade($flush,$dep);
		}

		foreach($flush as $val){
			if(property_exists($this->__resolved,$val)){
				unset($this->__resolved->$val);
			}
		}
	}

	protected function collect_dependency_cascade(&$receptacle,$dep){
		if($this->prop_defined($dep)){
			$deps = $this->get_dependents($dep);
			foreach($deps as $subdep){
				if(!in_array($subdep,$receptacle)){
					$receptacle[] = $subdep;
					$this->collect_dependency_cascade($receptacle,$subdep);
				}
			}
		}
	}

	protected function debugger($data=null){
		exit(print_r($data,true));
	}

	protected function get_schema(){ return $this->__schema; }

	protected function get_scope($context=array()){
		$scope = is_array($this->__scope) ? $this->__scope : array();
		$scope[$this->scope_name] = $this;
		if(!is_null($this->__container)){
			if(method_exists($this->__container,'get_scope')){
				$upper_scope = $this->__container->get_scope();
				$scope = array_merge($scope,$upper_scope);
			}else{
				$scope['container'] = $this->__container;
			}
		}
		return array_merge($scope,$context);
		//EMR 2016-07-19: Recursive memory-consumption nightmare:
		//return array_merge(array_merge($scope,$this->get_properties()),$context);

	}

	protected function get_scope_name(){ $class = get_class($this); return $class::scope_name; }

	protected function get_internal_state(){ return $this->__resolved; }

	protected function get_providers(){ return $this->__providers; }

	protected function get_accessors(){ return $this->__accessors; }

	protected function get_properties(){
		if(is_object($this->__schema) && property_exists($this->__schema,'properties')){
			$props = array_keys((array)$this->__schema->properties);
			$vals = array();

			foreach($props as $prop){
				if($this->cached_value_exists($prop)){
					$vals[$prop] = $this->__resolved->$prop;
				}else{
					$tmp = $this->get_di_property( $prop, $this->__schema->properties->$prop );
					if(property_exists($this->__schema->properties->$prop,'cached') && $this->__schema->properties->$prop->cached){
						$this->set_and_resolve( $prop, $tmp );
					}
					$vals[$prop] = $tmp;
				}
			}

			return $vals;
		}else return array();
	}

	protected function get_dependents($prop){
		return is_object($this->__providers) && property_exists($this->__providers,$prop)
			?	$this->__providers->$prop
			:	array();
	}

	protected function get_di_property( $prop, $spec ){
		$intermediate_method = "get_{$spec->accessor}_property";
		$return_value = $this->$intermediate_method( $spec, $prop );
		return $return_value;
	}

	protected function get_ci_property( $spec, $prop ){
		$stillGood = true;
		$here = $this->__fw;
		$steps = explode('/',trim($spec->path));
		$stepsCopy = $steps;
		do{
			$next = array_shift($steps);
			is_object($here)
				?	$temp = $here->$next
				:	(is_array($here)
					?	$temp = $here[$next]
					:	die("error: could not resolve value for property: '{$prop}'."));
			if(empty($steps)){
				return $temp;
			}else{
				$here = $temp;
			}
		}while($stillGood && !empty($steps));
		return isset($result)
			?	$result
			:	die("error: could not resolve value for property: '{$prop}'.");
	}

	protected function get_db_property( $spec, $context=array() ){
		if(!(property_exists($spec,'query'))){
			$outcome = 'no query specified for db-dependency';
			die(print_r(compact('outcome','spec'),true));
		}

		$scope = property_exists($spec,'dependencies')
			?	$this->resolve_dependencies($spec->dependencies)
			:	array($this->scope_name => $this);
		$query = $this->resolve_di_string( $spec->query, array_merge($scope,(array)$context) );

		if(property_exists($spec,'temporary_table') && is_string($spec->temporary_table)){
			$tableName = $spec->temporary_table;
			$this->create_temporary_table( $tableName, $query,
				property_exists($spec,'primary_key') ? $spec->primary_key : null
			);
		}

		try{
			$q = isset($tableName)
				?	$this->__fw->db->query("SELECT * FROM {$tableName}")
				:	$this->__fw->db->query($query);
		}catch(Exception $e){
			$msg = $e->getMessage();
			$db = $this->__fw->db;
			exit(print_r(compact('msg','db','query','tableName'),1));
		}

		return property_exists($spec,'process_fn')
			?	$this->resolve_fn_string($spec->process_fn,array_merge($scope,array('recordset'=>$q->result_array())))
			:	$q->result_array();
	}

	protected function get_api_property( $spec ){
		$deps = property_exists( $spec, 'dependencies' )
			?	$this->resolve_dependencies($spec->dependencies)
			:	array($this->scope_name => $this);

		$endpoint = is_object($spec->endpoint)
			?	$this->resolve_generic_getter($spec->endpoint,$deps)
			:	$this->resolve_di_string($spec->endpoint,$deps);

		$params = property_exists($spec,'params')
			?	$this->resolve_params($spec->params,$deps)
			:	array();

		$type = property_exists($spec,'type')
			?	strtoupper($spec->type)
			:	(array_key_exists('post',$params)
				? 'POST'
				: 'GET');

		if(array_key_exists('get',$params)){
			$qstring = http_build_query($params['get']);
			if(substr($endpoint,-1,1)=='?')
				$endpoint = substr($endpoint,0,-1);
			$endpoint = "{$endpoint}?{$qstring}";
		}

		$curl = curl_init();

		if(array_key_exists('post',$params)){
			$postbody = property_exists($spec,'post_as_json') && $spec->post_as_json===false
				?	$params['post']
				:	json_encode($params['post']);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $postbody);
		}

		if(array_key_exists('headers',$params)){
			$headers = $this->resolve_headers($params->headers,$deps);
		}

		if($type!=='GET'){
			curl_setopt($curl, CURLOPT_POST, true);
			if( in_array(strtolower($type),array('put','delete')) ){
				curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($type));
				$headers[] = 'Content-Length: '.strlen($postbody);
			}
		}

		if(isset($headers)){
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		}

		curl_setopt($curl, CURLOPT_URL, $endpoint);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

		$raw_response = curl_exec($curl);

		curl_close($curl);

		if(property_exists($spec,'response_type')){
			switch(strtolower($spec->response_type)){
				case 'json':
					$response = json_decode($raw_response);
				break;

				default:
					$response = trim($raw_response);
				break;
			}
		}

		return property_exists($spec,'return_type')
			?	($spec->return_type=='object'
				?	(object)$response
				:	($spec->return_type=='array'
					?	(array)$response
					:	($spec->return_type=='json'
						?	(property_exists($spec,'response_type') && $spec->response_type=='json'
							?	$raw_response
							:	json_encode($response))
						:	$response)))
			:	$response;
	}

	protected function get_fn_property( $spec, $prop ){
		return $this->resolve_fn_string(
			$spec->function,
			property_exists($spec,'dependencies')
				?	$this->resolve_dependencies($spec->dependencies)
				:	array($this->scope_name => $this)
		);
	}

	protected function get_ui_property( $spec, $prop ){
		$obj = $this;
		$temp = $this->__fw->input->get_post($spec->parameter,true);
		return is_null($temp) && property_exists($spec,'default')
			?	(is_object($spec->default)
				?	$this->get_di_property( $prop, $spec->default )
				:	$spec->default)
			:	(property_exists($spec,'type')
				?	($spec->type==='boolean'
					?	(is_numeric($temp)
						?	$temp > 0
						:	false)
					:	(is_bool($temp)
						?	$temp
						:	(is_string($temp)
							?	strtolower($temp)=='true'
							:	!!$temp
						)
					)
				)
				:	$temp
			);
	}

	protected function prop_defined($prop){
		return is_object($this->__schema)
			&& property_exists($this->__schema,'properties')
			&& property_exists($this->__schema->properties,$prop);
	}

	protected function prop_cached($prop){
		return $this->prop_defined($prop)
			&& property_exists($this->__schema->properties->$prop,'cached')
			&& !!$this->__schema->properties->$prop->cached;
	}

	protected function cached_value_exists($prop){
		return is_object($this->__resolved)
		&& property_exists($this->__resolved,$prop);
	}

	protected function init_methods( $methods ){
		$this->__methods = new stdClass;
		foreach($methods as $name => $spec){
			$args = is_object($spec) && property_exists($spec,'args') ?	implode(',',$spec->args) : '';
			$fn = is_object($spec) ? $spec->function : $spec;
			$this->__methods->$name = $this->create_bound_closure($args,$fn);
		}
	}

	protected function create_bound_closure($args='',$fn){
		$innerFn = is_string($fn) ? $fn : implode("\n",$fn);
		$outerFn = "\$closure=function({$args}){{$innerFn}};\$closure=\$closure->bindTo(\$that);return \$closure;";
		//$factory = create_function('$that',$outerFn);
		$factory = self::CreateFunction('$that',$outerFn);
		return $factory($this);
	}

	protected function init_properties( $spec ){
		$this->__fw =& get_instance();
		$this->__ui = new stdClass;
		$this->__providers = new stdClass;
		$this->__resolved = new stdClass;
		$this->__accessors = array_fill_keys(array('ci','db','ui','fn','api'),array());

		foreach( $spec as $property_name => $descriptor ){
			if(is_object($descriptor)){
				if(property_exists( $descriptor,'accessor' )){
					if(property_exists( $descriptor, 'dependencies' )){
						foreach( $descriptor->dependencies as $provider ){
							property_exists($this->__providers, $provider )
								?	array_push($this->__providers->$provider,$property_name)
								:	$this->__providers->$provider = array($property_name);
						}
					}

					array_key_exists($descriptor->accessor,$this->__accessors)
						?	array_push($this->__accessors[$descriptor->accessor],$property_name)
						:	$this->__accessors[$descriptor->accessor] = array($property_name);

					if(property_exists($descriptor,'default') && is_object($descriptor->default)){
						if(!array_key_exists($descriptor->default->accessor,$this->__accessors)){
							$this->__accessors[$descriptor->default->accessor] = array();
						}
						if(!in_array($property_name,$this->__accessors[$descriptor->default->accessor])){
							array_push($this->__accessors[$descriptor->default->accessor],$property_name);
						}
					}
				}else{
					$this->__resolved->$property_name = $descriptor;
				}
			}else{
				$this->__resolved->$property_name = $descriptor;
			}
		}
	}

	protected function di_get_fail($prop){
		$class = get_class($this);
		$prob = 'no property?';
		$keys = property_exists($this->__schema,'properties')
			?	array_keys((array)$this->__schema->properties)
			:	array();
		self::CrashBlob(compact('prop','prop_exists','prob','keys','class'));
	}

	protected function di_call_fail($method,$args){
		$class = get_class($this);
		$prob = 'no method?';
		$keys = property_exists($this->__schema,'properties')
			?	array_keys((array)$this->__schema->properties)
			:	array();
		self::CrashBlob(compact('method','args','prob','keys','class'));
	}

	protected function resolve_params( $params, $deps ){
		$output = array();
		foreach($params as $paramType => $paramsObject){
			$output[$paramType] = array();
			foreach($paramsObject as $name => $descriptor){
				$output[$paramType][$name] = $this->resolve_generic_getter($descriptor,$deps);
			}
		}
		return $output;
	}

	protected function resolve_recursive_array( $array, $deps ){
		$output = array();
		foreach($array as $value){
			$output[] = $this->resolve_recursive_struct( $value, $deps );
		}
		return $output;
	}

	protected function resolve_recursive_object( $obj, $deps ){
		$output = new stdClass;
		foreach($obj as $key => $value){
			$output->$key = $this->resolve_recursive_struct( $value, $deps );
		}
		return $output;
	}

	protected function resolve_recursive_struct($input,$deps=array()){
		return is_scalar($input)
		 	?	(is_string($input)
				?	$this->resolve_di_string($input,$deps)
				:	$input)
			:	(is_array($input)
				?	$this->resolve_recursive_array($input,$deps)
				:	(property_exists($input,'accessor')
					?	$this->resolve_generic_getter($input,$deps)
					:	$this->resolve_recursive_object($input,$deps)
				)
			);
	}

	protected function resolve_getterable_prop($obj,$prop,$default=null,$context=array()){
		$getters = property_exists($obj,'getters');
		return property_exists($obj,$prop)
			?	(is_object($obj->$prop) ? $this->resolve_generic_getter($obj->$prop,$context) : $obj->$prop)
			:	($getters && property_exists($obj->getters,$prop)
				?	$this->resolve_generic_getter($obj->getters->$prop,$context)
				:	($getters && property_exists($obj->getters,'value') && is_object($obj->getters->value) && property_exists($obj->getters->value,$prop)
					?	$this->resolve_generic_getter($obj->getters->value->$prop,$context)
					:	$default
				)
			);
	}

	protected function resolve_dependencies( $deps ){
		$scope = array($this->scope_name => $this);
		foreach($deps as $idx => $prop){
			$scope[$prop] = $this->$prop;
		}
		return $scope;
	}

	protected function resolve_generic_getter( $spec, $context=array() ){
		$scope = $this->get_scope( $context );
		if(is_string($spec)){
			return $this->resolve_di_string($spec,$scope);
		}elseif(is_object($spec)){
			if(property_exists($spec,'accessor')){
				switch($spec->accessor){
					case 'lookup':
						$lookupIndex = $spec->index;
						$lookupValues = $spec->values;
						return is_array($lookupValues) && is_numeric($lookupIndex)
							?	$lookupValues[(int)$lookupIndex]
							:	$lookupValues->$lookupIndex;
					break;

					case 'fn':
						return $this->resolve_fn_string( $spec->function, $scope );
					break;

					case 'db':
						return $this->get_db_property( $spec, $scope );
					break;

					case 'ci':
						return $this->get_ci_property( $spec );
					break;

					default:
						$nm = $spec->accessor;
						if(array_key_exists($nm,$scope)){
							if(property_exists($spec,'key')){
								$keyName = $spec->key;
								if(	is_array($scope[$nm])	){
									return array_key_exists($keyName,$scope[$nm])
										?	$scope[$nm][$keyName]
										:	die("key not found context[{$nm}][{$keyName}]");//$context[$nm];
								}elseif(is_object($scope[$nm])){
									return property_exists($scope[$nm],$keyName)
										?	$scope[$nm]->$keyName
										:	die("key not found context[{$nm}]->{$keyName}");
								}else{
									die("key not found context[{$nm}][{$keyName}]");
								}
							}else{
								return $scope[$nm];
							}
						}
					break;
				}
			}elseif(property_exists($spec,'source')){
				return $this->{$spec->source};
			}
		}
	}

	protected function resolve_fn_string( $fn, $deps ){



		$innerFn = is_string($fn) ? $fn : implode("\n",$fn);
		$outerFn = "\$closure=function(\$scope){ extract(\$scope);\n{$innerFn}; };\$closure=\$closure->bindTo(\$that);return \$closure;";
		if(!is_string($outerFn)){
			exit(print_r(compact('innerFn','outerFn','fn'),true));
		}
		//$factory = create_function('$that',$outerFn);
		$factory = self::CreateFunction('$that',$outerFn);


		$lambda = $factory($this);
		$output = $lambda($deps);
		return $output;
		/*
		if(!is_string($fn)){
			$fn = implode("\n",$fn);
		}
		$lambda = create_function('$scope',"extract(\$scope);\n{$fn};");
		$output = $lambda($deps);
		return $output;
		*/
	}

	protected function resolve_di_string($str,$context=array()){
		$pre = '(?<=\{\$)';
		$ctr = '[a-z|_][a-z|0-9|_]+';
		$suf = '(?:\[[^\]]+\]|->[a-z|_][a-z|0-9|_]+)*';

		if(!is_string($str)){
			if(is_array($str)){
				$str = implode("\n",$str);
			}elseif(is_object($str)){
				$str = $this->resolve_generic_getter($str,$context);
			}
		}

		$output = $str;

		if(preg_match_all("/{$pre}{$ctr}{$suf}/i",$str,$rawmatches)){
			foreach($rawmatches[0] as $match_idx => $match_str){
				$literal = '$'.$match_str;
				//$lambda = create_function('$scope',"extract(\$scope);\n return {$literal};");
				$lambda = self::CreateFunction('$scope',"extract(\$scope);\n return {$literal};");
				$replace_with = $lambda($context);
				$output = str_replace('{$'.$match_str.'}',$replace_with,$output);
			}
		}
		return $output;
	}
}
