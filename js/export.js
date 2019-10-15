if( typeof window.DMSA==='undefined' ) window.DMSA = {};
(function($){
	var FW=window.DMSA;

	//if( $('head link[href*="reporting_css"]').length===0 ){
	if( $('link[href*="reporting_css"]').length===0 ){
		$('head').append('<link rel="stylesheet" type="text/css" href="/modules/reporting/assets/css/reporting_css.css" />');
	}

	$(document).ready(function(){
		var
		$module = FW.current_module,
		$timers = $module.$timers,
		$ajaxes = $module.$ajax,
		$access = {},
		$reports = {
			default:	{
				id:				'',
				title:			'Select a Report',
				description:	'We have been working to unify Firefox Developer tools and Firebug for some time. From Firefox 49, we’ll be shipping Firebug.next built-in. For Firebug users this means many new features with the look and feel that you’re familiar with.'
			}
		},
		$state = (function(){
			var
			selected_report_id = null,
			loaded_report_id = null,
			facade = new Object();

			Object.defineProperties(facade,{
				selected_report_id:	{
					enumerable:	true,
					get:	function(){ return selected_report_id; },
					set:	function(id){
						selected_report_id = id;
						display_report_info(id);
					}
				},
				loaded_report_id:	{
					enumerable:	true,
					get:	function(){ return loaded_report_id; },
					set:	function(id){
						loaded_report_id = id;
						load_selected_report();
					}
				}
			});

			return facade;
		})(),
		$report_selection_form = $('#datasource-form').dynaform({
			fn:	{
				choose_report:	function(e){ $state.loaded_report_id = $state.selected_report_id; },
				enable_choose_btn: 	function(){ return $('#choose_report_btn').unlock(); },
				disable_choose_btn:	function(){	return $('#choose_report_btn').lock(); }
			},
			fields:	{
				has_access:	{
					value:		"0",
					validate:	function( value, proxy ){
						return value==1
							?	true
							:	value;
					}
				},
				report_id:	{
					value:		null,
					validate:	function( value, proxy ){
						return value!=null && value!==''
							?	($state.loaded_report_id==null
								?	true
								:	(value != $state.loaded_report_id
									?	true
									:	''))
							:	'Please select a report';
					},
					onChange:	function( e, proxy ){
						var selected_value = proxy.value;
						$state.selected_report_id = selected_value;

						$('#report-selector-pane .checking-indicator').removeClass('active');
						if( $.type($access[selected_value])==='undefined'){
							proxy.formProxy.fields.has_access.set_value(0);
							$('#report-selector-pane .checking-indicator').addClass('active');
							$.ajax({
								type: "GET",
								dataType: 'json',
								url: 'reporting/reporting_controller/check_requirements/'+String(selected_value),
								success: function(response){
									var hasAccess = true;
									$access[selected_value] = response;
									update_selected_report_requires_block(proxy.formProxy);
									$('#report-selector-pane .checking-indicator').removeClass('active');
								}
							});
						}else{
							update_selected_report_requires_block(proxy.formProxy);
						}
					}
				}
			},
			validate:	function(){ this.enable_choose_btn(); },
			invalidate:	function(){ this.disable_choose_btn(); }
		}),
		$workbook = new Workbook( $('#workbook-wrapper') );

		$module.reports = $reports;
		$module.access = $access;

		$module.get_active = function(e){
			e.preventDefault();
			$.ajax({
				type: "GET",
				dataType: 'json',
				url: 'reporting/reporting_controller/get_active_report',
				success: function(response){
					console.log({active_report: response});
				}
			});
		}

		$module.debug_session = function(e){
			e.preventDefault();
			$.ajax({
				type: "GET",
				dataType: 'json',
				url: 'reporting/reporting_controller/debug_session',
				success: function(response){
					console.log({debug: response});
				}
			});
		}

		$module.clear_active = function(e){
			e.preventDefault();
			$.ajax({
				type: "GET",
				dataType: 'json',
				url: 'reporting/reporting_controller/clear_active',
				success: function(response){
					console.log({debug: response});
				}
			});
		}

		$module.remove_report_entity = function(e){}

		$module.show_top_section = show_top_section;
		$module.show_mid_section = show_mid_section;
		$module.update_spreadsheet = update_spreadsheet;

		$module.remove_spreadsheet = function(e){}

		$module.workbook = $workbook;
		$module.reporting_state = $state;
		$module.report_selection_form = $report_selection_form.dynaform();

		get_available_reports();

		function seek_property( baseData, paths, failValue ){
			var
			property,
			targetReached = false,
			traversePath = function( path ){
				var
				targetReachable = true,
				steps = path.split('.'),
				context = baseData;

				$.each(steps,function(i,step){
					//if($.type(context[step])!=='undefined'
					if(
						$.inArray( $.type(context),['array','object'] )!==-1
						&&
						$.type( context[step] )!=='undefined'
					){
						var tmp = context[step];
						context = tmp;
					}else{
						targetReachable = false;
						return false;
					}
				});

				if(targetReachable){
					property = context;
					targetReached = true;
				}

				return targetReached;
			}

			$.each(paths,function(i,path){
				traversePath( path );
				if(targetReached){
					return false;
				}
			});

			return property;

			//return targetReached ? property : failValue;
		}

		function show_top_section(){
			setTimeout(function(){
				$('#step-1-body').collapse('show');
				$('#step-2-body').collapse('hide');
			},200);
			$('a.accordion-toggle[href="#step-1-body"]').removeClass('collapsed');
			$('a.accordion-toggle[href="#step-2-body"]').addClass('collapsed');
			$('#datasource-form').removeClass('collapsed');
			$('#datasource-options').addClass('collapsed');
		}

		function show_mid_section(){
			$('a.accordion-toggle[href="#step-2-body"]').removeClass('collapsed');
			$('a.accordion-toggle[href="#step-1-body"]').addClass('collapsed');
			$('#datasource-form').addClass('collapsed');
			$('#datasource-options').removeClass('collapsed');
			setTimeout(function(){
				$('#step-2-body').collapse('show');
				$('#step-1-body').collapse('hide');
			},200);
		}

		function update_selected_report_requires_block(formProxy){
			var
			i = 0,
			hasAccess = true,
			checkResults = $access[$state.selected_report_id],
			lis = $('#report-info-pane ul.report-requires > li');

			$.each(checkResults,function(check,result){
				var li = $(lis[i++]);
				if(result!==true){
					hasAccess = false;
					li.removeClass('check-passed').addClass('check-failed');
				}else{
					li.removeClass('check-failed').addClass('check-passed')
				}
			});

			formProxy.fields.has_access.set_value( hasAccess ? 1 : 0 );
		}

		function clear_report_info_pane(){
			$('#report-info-pane').find('.report-title,.report-heading,.report-keywords,.report-metrics,.report-requires').empty();
		}

		function clear_report_options_form(){
			$module.datasource_options.cleanup();
			delete $module.datasource_options;
			$('#datasource-options-container').empty();
		}

		function display_report_info( report_id ){
			var
			key = report_id ? report_id : 'default',
			pieces = ['heading','keywords','metrics','requires'],
			colors = {
				metrics:	'blue',
				keywords:	'green'
			};

			$.each(pieces,function(i,piece){
				var
				target = $('#report-info-pane .report-'+piece),
				source = $reports[key].description[piece];
				target.empty();
				if($.type(source)!=='undefined'){
					if($.type(source)==='array'){
						if($.type(colors[piece])!=='undefined'){
							$.each(source,function(ii,ss){
								target.append( Tag('li.badge.badge-'+colors[piece],ss).toString() );
							 });
						}else{
							$.each(source,function(ii,ss){
								target.append( Tag('li',ss).toString() );
							});
						}
					}else{
						target.text(source);
					}
				}
			});

			if($.type($reports[key].requires)!=='undefined'){
				$.each($reports[key].requires,function(txt,obj){
					$('#report-info-pane .report-requires').append(
						Tag('li',txt).toString()
					);
				});
			}

			$('#datasource-form > div.box-header span.proper-title').text( $reports[key].title );
			$('#report-info-pane .report-title').text($reports[key].title);
		}

		function load_selected_report(){
			$('#datasource-form').addClass('ajax-working').lock();

			$report_selection_form.dynaform().disable_choose_btn();

			setTimeout(function(){
				$workbook.clear_spreadsheets( initialize_selected_report )
			},0);
		}

		function initialize_selected_report(){
			var report_id = $state.selected_report_id;

			setTimeout(function(){
				$ajaxes.load_selected_report = $.ajax({
					type: "GET",
					dataType: 'json',
					url: 'reporting/reporting_controller/load_report/'+String(report_id),
					success: populate_report_options
				});
			},0);
		}

		function get_available_reports(){
			return $ajaxes.get_available_reports = $.ajax({
				type: "GET",
				dataType: 'json',
				url: 'reporting/reporting_controller/get_available_reports',
				success: populate_available_reports_dropdown
			});
		}

		function update_and_export( formProxy ){
			formProxy.form.addClass('ajax-working');//.lock();
			//alert('333');
			return $ajaxes.update_report_params = $.ajax({
				type: "POST",
				dataType: 'json',
				data: formProxy.values,
				url: 'reporting/reporting_controller/update_report_params',
				success: function( response ){
					if($('#form-decoy').length){ $('#form-decoy').remove(); }
					$('#reporting-wrapper').prepend( Tag('iframe#form-decoy[src="reporting/reporting_controller/export_workbook"][style="display:none;"]').toString() );
					if($.type(response.confirmation)==='object'){
						confirm_options_dialog( response, formProxy );
					}else{
						$workbook.update_report_preview( response.spreadsheets );
					}
					formProxy.form.removeClass('ajax-working');//.unlock();
				}
			});
		}

		function update_report_params( formProxy ){
			formProxy.form.addClass('ajax-working');
			//alert('354');
			return $ajaxes.update_report_params = $.ajax({
				type: "POST",
				dataType: 'json',
				data: formProxy.values,
				url: 'reporting/reporting_controller/update_report_params',
				success: function( response ){
					if($.type(response.confirmation)==='object'){
						confirm_options_dialog( response, formProxy );
					}else{
						$workbook.update_report_preview( response.spreadsheets );
					}
					formProxy.form.removeClass('ajax-working');//.unlock();
				}
			});
		}

		function update_spreadsheet( sheetId ){
			//alert('372');
			return $ajaxes.update_report_params = $.ajax({
				type: "POST",
				dataType: 'json',
				data: $module.datasource_options.values,
				url: 'reporting/reporting_controller/update_report_params',
				success: function( response ){
					$workbook.update_spreadsheet( sheetId, response.spreadsheets[sheetId] );
				}
			});
		}

		function confirm_options_dialog( data, formProxy ){
			var
			msg,
			options = data.options,
			confirm = data.confirmation,
			$modal = $('#confirm_options_modal');

			if($.type(confirm.title)!=='undefined'){
				$modal.find('.modal-title').text( confirm.title );
			}

			if($.type(confirm.message)!=='undefined'){
				$modal.find('.modal-body').html('<p>'+($.type(confirm.message)==='array'?confirm.message.join(' '):confirm.message)+'</p>');
			}

			$modal.one('click','button[data-button="confirm"]', confirm_options_handler.bind( null, formProxy ) );

			$modal.one({
				'show.bs.modal':	function(){ console.log('modal shown'); },
				'hide.bs.modal': 	confirm_options_stop_listening
			});

			$modal.modal('toggle',this);
		}

		function confirm_options_stop_listening(){
			console.log('confirm_options_stop_listening');
			$('#confirm_options_modal').off('click');
		}

		function confirm_options_handler( formProxy ){
			$('#confirm_options_modal').modal('hide');
			//alert('416');
			return update_report_params( formProxy );
			// $ajaxes.create_list = $.ajax({
			// 	type: "POST",
			// 	dataType: 'json',
			// 	data: $formProxy.values,
			// 	url: 'mobile_response/mobile_response_controller/create_campaign',
			// 	success: function( response ){
			// 		if( response===true ){
			// 			refresh_campaigns_table();
			// 			create_campaign_success( $formProxy.values.campaign_name );
			// 		}else{
			// 			create_campaign_error( response );
			// 		}
			// 	},
			// 	error: function( jqXHR, textStatus, errorThrown ){
			// 		create_campaign_error( textStatus );
			// 	},
			// 	complete: function(){
			// 		$interior.removeClass('ajax-working');
			// 		$form.unlock();
			// 		$modal.modal('hide');
			// 	}
			// });
		}

		function populate_report_options( data ){
			var
			layout = [],
			containers = {},
			flexChildren = {},
			dynaformFlavor = $.type(data.flavor)==='string'
				?	data.flavor
				:	'fluid',
			dynaformOptions = {
				fn:			{
					enable_preview_btn: 	function(){ return $('#preview_report_btn').unlock(); },
					disable_preview_btn:	function(){	return $('#preview_report_btn').lock(); },
					preview_report:			function(){ return update_report_params( this ); },
					export_report:			function(){ return update_and_export( this ); },
					cleanup:				function(){
						this.form.find('[data-dynaform-item="buttons"]').empty();
						this.form.find('div.ibutton-container input[type="checkbox"]').each(function(i,e){
							$(e).iButton('destroy');
						});
						this.form.find('input[type="checkbox"],input[type="radio"]').each(function(i,e){
							$(e).iCheck('destroy');
						});
					},
					start_over:				function(){ setTimeout(clear_report_options_form,0); return show_top_section(); },
					select_all:				function(e,formProxy){
						e.preventDefault();
						$(e.target).closest('fieldset').find('.checkbox-group').find('input[type="checkbox"]').iCheck('check');
					},
					select_none:			function(e,formProxy){
						e.preventDefault();
						$(e.target).closest('fieldset').find('.checkbox-group').find('input[type="checkbox"]').iCheck('uncheck');
					}
				},
				fields:		{},
				validate:	function(){ this.hide_errors(); this.enable_preview_btn(); },
				invalidate: function(){ this.show_errors(15000); this.disable_preview_btn(); },
				buttons:	{
					preview_report_btn:	{
						class: 'btn-default',
						fn:	'preview_report',
						icon: 'icon-eye-open',
						label: 'Preview'
					},
					export_report_btn: {
						class: 'btn-default',
						fn: 'export_report',
						icon: 'icon-file-alt',
						label: 'Export'
					}
				}
			};

			$('#datasource-options').removeClass('fluid-dynaform liquid-dynaform')
									.addClass( dynaformFlavor+'-dynaform' );

			if($.type(data.functions)==='object'){
				$.each(data.functions,function(fName,fSpec){
					dynaformOptions.fn[fName] = new Function(fSpec.args, $.type(fSpec.body)==='array' ? fSpec.body.join('\n') : fSpec.body );
					//if($.type(window.DMSA.current_module[fName])==='undefined'){
					//	window.DMSA.current_module[fName] = new Function(fSpec.args, $.type(fSpec.body)==='array' ? fSpec.body.join('\n') : fSpec.body );
					//}
				});
			}

			$('#datasource-form').removeClass('ajax-working').unlock();

			if($.type($module.datasource_options)==='object'){
				clear_report_options_form();
				//$('#datasource-options').addClass('ajax-working');
			}

			$('#datasource-options').removeClass('ajax-working');

			setTimeout(function(){
				if($.type(data.components)==='object'){
					build_components_layout( data, dynaformOptions );
				}

				$('#datasource-options').dynaform( dynaformOptions );

				$module.datasource_options = $('#datasource-options').dynaform();

				setTimeout(show_mid_section,0);

			},0);
		}

		function build_layout(layoutSpec,layoutContainer,layoutObj,layoutClasses){
			var
			containerClasses = $.type(layoutClasses)==='array' ? layoutClasses.join(' ') : '',
			childClasses = [],
			actualSpec;

			if(containerClasses!==''){
				layoutContainer.addClass(containerClasses);
			}

			if( $.type(layoutSpec)==='object' ){
				$.each(layoutSpec,function(classname,interior){
					childClasses = $.trim(classname).split(' ');
					actualSpec = interior;
				});
			}else{
				actualSpec = layoutSpec;
			}

			switch($.type(actualSpec)){
				case 'array':
					var flexContainer = $( Tag('div'+(containerClasses.length
							? '.'+containerClasses
							: '')+'[data-options-row="'+String(layoutObj.index)+'"]').toString() );
					layoutContainer.append( flexContainer );
					layoutObj.index++;
					$.each(actualSpec,function(layoutIndex,layoutItem){
						build_layout(layoutItem,flexContainer,layoutObj,childClasses);
					});
				break;

				case 'string':
					if(childClasses.length){
						layoutObj.classes[actualSpec] = childClasses;
					}
					layoutObj.layout.push( actualSpec );
					layoutObj.containers[actualSpec] = layoutContainer;
				break;
			}

		}

		function build_components_layout( data, dynaformOptions ){
			var
			layout = [],
			layoutIdx = 0,
			containers = {},
			layoutClasses = {},
			outermostContainer = $('#datasource-options-container');

			if($.type(data.layout)==='object'){
				$.each(data.layout,function(classNames,actualSpec){
					build_layout(
						actualSpec,
						outermostContainer,
						{
							layout:		layout,
							containers:	containers,
							index:		layoutIdx,
							classes:	layoutClasses
						},
						classNames.split(' ')
					);
				});
			}else{
				if($.type(data.layout)==='array'){
					$.each(data.layout,function(i,e){
						build_layout(
							e,
							outermostContainer,
							{
								layout:		layout,
								containers:	containers,
								index:		layoutIdx,
								classes:	layoutClasses
							}
						);
					});
				}else{
					$.each(data.components,function( component_id, spec ){
						layout.push(component_id);
					});
				}
			}

			$.each(layout,function( idx, component_id ){
				var
				options,
				spec = data.components[component_id],
				component = $.type(spec.parameter)==='undefined' ? component_id : spec.parameter,
				container = $.type(containers[component_id])==='undefined'
					? 	outermostContainer
					: 	containers[component_id];

				if($.type(layoutClasses[component_id])==='array'){
					spec.classes = layoutClasses[component_id];
				}

				switch( spec.type )
				{
					case 'text':
						options = build_basic_text_component( component_id, spec, dynaformOptions, container );
					break;

					case 'toggle':
						options = build_toggle_component( component_id, spec, dynaformOptions, container );
					break;

					case 'checkboxes':
						options = $.type( spec.style )==='string' && spec.style=='adaptive'
							?	build_adaptive_checkboxes_component( component_id, spec, dynaformOptions, container )
							:	build_checkboxes_component( component_id, spec, dynaformOptions, container );
						//options = build_checkboxes_component( component_id, spec, dynaformOptions, container );
					break;

					case 'radio':
						options = build_radio_component( component_id, spec, dynaformOptions, container );
					break;

					case 'dropdown':
						options = build_dropdown_component( component_id, spec, dynaformOptions, container );
					break;

					case 'datepicker':
						options = build_datepicker_component( component_id, spec, dynaformOptions, container );
					break;

					case 'daterange':
						options = build_daterange_component( component_id, spec, dynaformOptions, container );
					break;
				}

				//console.log('returned options',{component: component_id, options: options});

				$.each(['onChange','onKeyup','onFocus','onBlur'],function(prodIdx,propName){
					if( $.type(spec[propName])!=='undefined' ){
						var newFunc = new Function(
							spec[propName].args,
							$.type(spec[propName].body)==='array'
								? spec[propName].body.join('\n')
								: spec[propName].body
						);
						if($.type(options)==='array'){
							$.each(options,function(i,subComponent){
								$.each(subComponent,function(subName,subOptions){
									subOptions[propName] = newFunc;
								});
							});
						}else{
							options[propName] = newFunc;
						}
					}
				});
			});
		}

		function build_dropdown_component( id, schema, dynaformOptions, container ){
			var
			name = $.type(schema.parameter)==='undefined' ? id : schema.parameter,
			selector = Tag('select.input[name="'+name+'"][style="max-width:100%;"]'),
			wrapper = Tag('div.input-wrapper',selector),
			multiple = !!schema.multiple,
			classes = $.type(schema.classes)==='array' ? ('.'+schema.classes.join('.')) : '',
			fieldset = Tag('fieldset.dropdown-wrapper'+classes,Tag('legend.h5',schema.label),wrapper),
			preselected = $.type(schema.value)==='undefined' ? null : schema.value,
			optsType = $.type(schema.options),
			select2Options = $.type(schema.select2)==='object' ? schema.select2 : {},
			maxOptionLength = 0,
			optionInitializerFn,
			dynaformFieldOptions = {
				value: multiple ? (preselected==null ? [] : preselected) : preselected
			};

			if($.type(schema.placeholder)==='string'){ select2Options.placeholder = schema.placeholder; }

			if($.type(select2Options.multiple)==='undefined'){
				select2Options.multiple = multiple;
			}

			if($.type(select2Options.closeOnSelect)==='undefined'){
				select2Options.closeOnSelect = !!multiple;
			}

			if(optsType==='array'){
				select2Options.data = [];

				$.each(schema.options,function(idx,ele){
					if($.type(ele)==='object' && $.type(ele.value)!=='undefined' && $.type(ele.label)!=='undefined'){
						maxOptionLength = ele.label.length > maxOptionLength ? ele.label.length : maxOptionLength;
						select2Options.data.push({ id: ele.value, text: ele.label });
						if(ele.value==preselected){
							selector.append( Tag('option'+(ele.value!=null && ele.value==preselected
								? 	'[selected="selected"]'
								: 	'')+'[value="'+ele.value+'"]',ele.label) );
						}
					}
				});

				if( $.type(schema.classes)==='array' && schema.classes.indexOf('autolength')!==-1 ){
					fieldset.addClass(
						maxOptionLength >= 30
							?	'wide-length'
							:	(maxOptionLength >= 18
								?	'medium-length'
								:	'short-length')
					);
				}

			}else{

				select2Options.allowClear = true;
				//select2Options.minimumInputLength = 0;
				//select2Options.minimumInputLength = 2;

				if($.type(select2Options.minimumInputLength)==='undefined'){
					select2Options.minimumInputLength = 2;
				}

				select2Options.ajax = {
					url:		'reporting/reporting_controller/lookahead/'+schema.options.endpoint,
					dataType:	'json',
					delay:		250,
					data:		function(params){
						var dfrm, post = {};
						if( $.type(schema.options.also)==='array' ){

							dfrm = $( this ).closest('form').data('dynaform');

							$.each( schema.options.also, function( i, e ){
								if( $.type(dfrm.values[e])!=='undefined' ){
									post[e] = dfrm.values[e];
								}
							});
						}

						post[schema.options.lookup] = params.term;

						// console.log({
						// 	post:		post,
						// 	lookup:		schema.options.lookup,
						// 	term:		params.term
						// });

						return post;
					}
				};
			}

			if($.type(schema.validate)!=='undefined'){
				dynaformFieldOptions.validate = schema.validate;
			}

			$.each('onChange,onBlur,onFocus'.split(','),function(i,prop){
				if( $.type(schema[prop])!=='undefined' ){
					dynaformFieldOptions[prop] = new Function(schema[prop].args, $.type(schema[prop].body)==='array'
						?	schema[prop].body.join('\n')
						:	schema[prop].body
					);
				}
			});

			$(container).append( fieldset.toString() );

			$('select[name="'+name+'"]').select2( select2Options ).on('change',function(e){
				//console.log('select2 change');
				window.scrollTo(window.scrollX, window.scrollY+1);
			});

			dynaformOptions.fields[name] = dynaformFieldOptions;

			return dynaformFieldOptions;
		}

		function build_basic_text_component( id, schema, dynaformOptions, container ){
			var
			name = $.type(schema.parameter)==='undefined' ? id : schema.parameter,
			valueAtt = $.type(schema.value)!=='undefined' && schema.value!=null
				?	'[value="'+schema.value+'"]'
				:	'',
			classes = $.type(schema.classes)==='array' ? ('.'+schema.classes.join('.')) : '',
			wrapper = Tag('div.input-wrapper',Tag('input#'+name+'[name="'+name+'"][type="text"]'+valueAtt)),
			fieldset = Tag('fieldset'+classes,Tag('legend.h5',schema.label),wrapper),
			prefilledValue = $.type(schema.value)==='undefined' ? null : schema.value,
			dynaformFieldOptions = {
				value: prefilledValue
			};

			if($.type(schema.validate)!=='undefined'){
				dynaformFieldOptions.validate = schema.validate;
			}

			$.each('onChange,onBlur,onFocus'.split(','),function(i,prop){
				if( $.type(schema[prop])!=='undefined' ){
					dynaformFieldOptions[prop] = new Function(schema[prop].args, $.type(schema[prop].body)==='array'
						?	schema[prop].body.join('\n')
						:	schema[prop].body
					);
				}
			});

			$( container ).append( fieldset.toString() );
			dynaformOptions.fields[name] = dynaformFieldOptions;

			return dynaformFieldOptions;
		}

		function build_toggle_component( id, schema, dynaformOptions, container ){
			var
			name = $.type(schema.parameter)==='undefined' ? id : schema.parameter,
			valueAtt = '[value="1"]',
			checkedAtt = $.type(schema.value)==='undefined' || !schema.value
			  	?	'[checked=""]'
				:	'[checked="checked"]',
			classes = $.type(schema.classes)==='array' ? ('.'+schema.classes.join('.')) : '',
			wrapper = Tag('div.input-wrapper',Tag('input#'+name+'.toggle-unstyled[name="'+name+'"][type="checkbox"]'+valueAtt+checkedAtt)),
			flexClass = $.type(schema.flexClass)!=='undefined' ? schema.flexClass : '',
			fieldset = Tag('fieldset'+classes+'.toggle-wrapper'+flexClass,Tag('legend.h5',schema.label),wrapper),
			dynaformFieldOptions = {};

			if($.type(schema.validate)!=='undefined'){
				dynaformFieldOptions.validate = schema.validate;
			}

			$.each('onChange,onBlur,onFocus'.split(','),function(i,prop){
				if( $.type(schema[prop])!=='undefined' ){
					dynaformFieldOptions[prop] = new Function(schema[prop].args, $.type(schema[prop].body)==='array'
						?	schema[prop].body.join('\n')
						:	schema[prop].body
					);
				}
			});

			$( container ).append( fieldset.toString() );
			dynaformOptions.fields[name] = dynaformFieldOptions;

			return dynaformFieldOptions;
		}

		function build_legend_with_toolbar( label ){
			return XTag('legend.h5.with-toolbar',
				XTag('span', label ),
				XTag('div.btn-toolbar.inline-toolbar',
					XTag('div.btn-group',
						XTag('button.btn.btn-default.tip[rel="tooltip"][data-placement="top"][data-dynaform-fn="select_all"][data-original-title="All"]',
							XTag('i.icon-plus')
						),
						XTag('button.btn.btn-default.tip[rel="tooltip"][data-placement="top"][data-dynaform-fn="select_none"][data-original-title="None"]',
							XTag('i.icon-ban-circle')
						)
					)
				)
			)
		}

		function build_legend_without_toolbar( label ){
			return XTag('legend.h5', XTag('span', label ) );
		}

		function build_checkboxes_component( id, schema, dynaformOptions, container ){
			var
			name = $.type(schema.parameter)==='undefined' ? id : schema.parameter,
			wrapper = $( XTag('div.input-wrapper.checkbox-group') ),
			testSubject,
			maxWidth,
			classes = $.type(schema.classes)==='array' ? ('.'+schema.classes.join('.')) : '',
			legend = $.type(schema.toolbar)==='undefined' || schema.toolbar==true
				?	build_legend_with_toolbar(schema.label)
				:	build_legend_without_toolbar(schema.label),
			fieldset =XTag(
				'fieldset'+classes,
				legend,
				wrapper
			),
			maxLabelLength = 0,
			verticalClass = {10:'lte-10',25:'lte-25',40:'lte-40',65:'lte-65',80:'lte-80',100:'lte-100'},
			dynaformFieldOptions = {
				value: []
			},
			checkboxes = [];

			if($.type(schema.validate)!=='undefined'){
				dynaformFieldOptions.validate = schema.validate;
			}

			$.each('onChange,onBlur,onFocus'.split(','),function(i,prop){
				if( $.type(schema[prop])!=='undefined' ){
					dynaformFieldOptions[prop] = new Function(schema[prop].args, $.type(schema[prop].body)==='array'
						?	schema[prop].body.join('\n')
						:	schema[prop].body
					);
				}
			});

			$.each( schema.options, function( idx, item ){
				var
				label = item.label,
				checkState = item.selected ? '[checked="checked"]' : '',
				checkboxElement = XTag('div.checkbox-wrapper',
					XTag('input.checkbox-unstyled[name="'+name+'"][type="checkbox"]'+checkState+'[value="'+String(item.value)+'"]'),
					XTag('label',label)
				);
				dynaformFieldOptions.value.push(item.value);
				wrapper.append(
					Tag('div.checkbox-wrapper',
						Tag('input.checkbox-unstyled[name="'+name+'"][type="checkbox"]'+checkState+'[value="'+String(item.value)+'"]'),
						Tag('label',label)
					)
				);
				wrapper.append( checkboxElement );

				if(label.length > maxLabelLength){
					maxLabelLength = label.length;
				}
			});

			$.each(verticalClass,function(lte,className){
				if( schema.options.length <= lte ){
					wrapper.addClass( className );
					return false;
				}
			});

			wrapper.addClass( maxLabelLength >= 30
				? 'single-column'
				: (maxLabelLength >= 18
					? (maxLabelLength >= 14
						? 'medium-columns'
						: 'loose-columns')
					: 'tight-columns'));

			$( container ).append( fieldset );

			dynaformOptions.fields[name] = dynaformFieldOptions;

			return dynaformFieldOptions;
		}

		function build_adaptive_checkboxes_component( id, schema, dynaformOptions, container ){
			var
			name = $.type(schema.parameter)==='undefined' ? id : schema.parameter,
			listElement, // = XTag('ul'),
			testSubject,
			maxWidth = 0,
			classes = $.type(schema.classes)==='array' ? ('.'+schema.classes.join('.')) : '',
			checkboxGroup = XTag('div.input-wrapper.checkbox-group'),
			legend = $.type(schema.toolbar)==='undefined' || schema.toolbar==true
				?	build_legend_with_toolbar(schema.label)
				:	build_legend_without_toolbar(schema.label),
			fieldset =XTag(
				'fieldset'+classes,
				legend
			),
			maxLabelLength = 0,
			verticalClass = {10:'lte-10',25:'lte-25',40:'lte-40',65:'lte-65',80:'lte-80',100:'lte-100'},
			dynaformFieldOptions = {
				value: []
			},
			checkboxes = [];

			//console.log({checkboxes_schema: schema, dformoptions: dynaformOptions});

			if($.type(schema.validate)!=='undefined'){
				dynaformFieldOptions.validate = schema.validate;
			}

			$.each('onChange,onBlur,onFocus'.split(','),function(i,prop){
				if( $.type(schema[prop])!=='undefined' ){
					dynaformFieldOptions[prop] = new Function(schema[prop].args, $.type(schema[prop].body)==='array'
						?	schema[prop].body.join('\n')
						:	schema[prop].body
					);
				}
			});

			$.each( schema.options, function( idx, item ){
				var
				label = item.label,
				checkState = item.selected ? '[checked="checked"]' : '',
				checkboxValue = String(item.value)
				checkboxElement =
				XTag('div.checkbox-wrapper',
					XTag('input.checkbox-unstyled[name="'+name+'"][type="checkbox"]'+checkState+'[value="'+checkboxValue+'"]'),
					XTag('label',label)
				);

				dynaformFieldOptions.value.push( checkboxValue );

				checkboxes.push( checkboxElement );

				if(label.length > maxLabelLength){
					maxLabelLength = label.length;
					testSubject = checkboxElement;
				}
			});

			maxWidth = measure_element( testSubject );

			listElement = XTag('ul[style="column-width:'+String( maxWidth + 5 )+'px;"]');

			checkboxGroup.append( listElement );

			fieldset.append( checkboxGroup );

			$( container ).append( fieldset );

			$.each( checkboxes, function( i, e ){
				listElement.append( XTag('li', e ) );
			});

			// console.log({
			// 	maxWidth:	 	maxWidth,
			// 	maxLabelLength:	maxLabelLength,
			// 	listElement: 	listElement,
			// 	testSubject:	testSubject
			// });

			dynaformOptions.fields[name] = dynaformFieldOptions;

			return dynaformFieldOptions;
		}

		function build_radio_component( id, schema, dynaformOptions, container ){
			var
			name = $.type(schema.parameter)==='undefined' ? id : schema.parameter,
			listElement, // = XTag('ul'),
			testSubject,
			maxWidth = 0,
			classes = $.type(schema.classes)==='array' ? ('.'+schema.classes.join('.')) : '',
			checkboxGroup = XTag('div.input-wrapper.checkbox-group'),
			legend = build_legend_without_toolbar( schema.label ), // $.type(schema.toolbar)==='undefined' || schema.toolbar==true
				// ?	build_legend_with_toolbar(schema.label)
				// :	build_legend_without_toolbar(schema.label),
			fieldset =XTag( 'fieldset'+classes, legend ),
			maxLabelLength = 0,
			verticalClass = {10:'lte-10',25:'lte-25',40:'lte-40',65:'lte-65',80:'lte-80',100:'lte-100'},
			dynaformFieldOptions = {value: null},
			checkboxes = [];

			//console.log({checkboxes_schema: schema, dformoptions: dynaformOptions});

			if($.type(schema.validate)!=='undefined'){
				dynaformFieldOptions.validate = schema.validate;
			}

			$.each('onChange,onBlur,onFocus'.split(','),function(i,prop){
				if( $.type(schema[prop])!=='undefined' ){
					dynaformFieldOptions[prop] = new Function(schema[prop].args, $.type(schema[prop].body)==='array'
						?	schema[prop].body.join('\n')
						:	schema[prop].body
					);
				}
			});

			$.each( schema.options, function( idx, item ){
				var
				label = item.label,
				checkState = item.selected ? '[checked="checked"]' : '',
				checkboxValue = String(item.value)
				checkboxElement =
				XTag('div.checkbox-wrapper',
					XTag('input.checkbox-unstyled[name="'+name+'"][type="radio"]'+checkState+'[value="'+checkboxValue+'"]'),
					XTag('label',label)
				);

				if( checkState ){
					dynaformFieldOptions.value = checkboxValue;
				};

				checkboxes.push( checkboxElement );

				if(label.length > maxLabelLength){
					maxLabelLength = label.length;
					testSubject = checkboxElement;
				}
			});

			maxWidth = measure_element( testSubject );

			listElement = XTag('ul[style="column-width:'+String( maxWidth + 5 )+'px;"]');

			checkboxGroup.append( listElement );

			fieldset.append( checkboxGroup );

			$( container ).append( fieldset );

			$.each( checkboxes, function( i, e ){
				listElement.append( XTag('li', e ) );
			});

			// console.log({
			// 	maxWidth:	 	maxWidth,
			// 	maxLabelLength:	maxLabelLength,
			// 	listElement: 	listElement,
			// 	testSubject:	testSubject
			// });

			dynaformOptions.fields[name] = dynaformFieldOptions;

			return dynaformFieldOptions;
		}

		function measure_element( elem ){
			var
			e = $( elem ), w;
			e.addClass('in-limbo').appendTo('body');

			w = elem.offsetWidth;

			e.detach().removeClass('in-limbo');

			return w;
		}

		function build_datepicker_component( id, schema, dynaformOptions, container ){
			var
			name = $.type(schema.parameter)==='undefined' ? id : schema.parameter,
			pickerFormat = $.type(schema.options.format)==='string' ? schema.options.format : 'yyyy-mm-dd',
			pickerFormatAsMoment = datepickerToMoment( pickerFormat ),
			initValue = $.type(schema.value)!=='undefined' ? moment(schema.value,'YYYY-MM-DD') : moment(),
			hiddenValue = initValue.format('YYYY-MM-DD'),
			hiddenValueAttribute = initValue!=null ? '[value="'+hiddenValue+'"]' : '',
			datepickerValueAttribute = initValue!=null ? '[value="'+initValue.format( pickerFormatAsMoment )+'"]' :	'',
			datepickerOptions = $.type(schema.options)==='object' ?	schema.options : {},
			dynaformFieldOptions = {value: hiddenValue},
			classes = $.type(schema.classes)==='array' ? ('.'+schema.classes.join('.')) : '',
			mergedOptions;

			$.each('datesDisabled,defaultViewDate,endDate,startDate'.split(','),function(i,prop){
				if($.type(datepickerOptions[prop])!=='undefined' && datepickerOptions[prop]!=null){
					if($.type(datepickerOptions[prop])==='array'){
						var replacementArray = [];
						$.each(datepickerOptions[prop],function(ii,date){
							if(/^[0-9]{4}\-[0-9]{2}\-[0-9]{2}$/.test(date)){
								replacementArray.push( moment(date,'YYYY-MM-DD').format( pickerFormatAsMoment )	);
							}
						});
						datepickerOptions[prop] = replacementArray;
					}else{
						if(/^[0-9]{4}\-[0-9]{2}\-[0-9]{2}$/.test(datepickerOptions[prop])){
							datepickerOptions[prop] = moment(datepickerOptions[prop],'YYYY-MM-DD').format( pickerFormatAsMoment );
						}
					}
				}
			});

			mergedOptions = $.extend( true, datepickerOptions, { });

			if($.type(schema.validate)!=='undefined'){
				dynaformFieldOptions.validate = schema.validate;
			}

			$.each('onChange,onBlur,onFocus'.split(','),function(i,prop){
				if( $.type(schema[prop])!=='undefined' ){
					dynaformFieldOptions[prop] = new Function(schema[prop].args, $.type(schema[prop].body)==='array'
						?	schema[prop].body.join('\n')
						:	schema[prop].body
					);
				}
			});

			$( container ).append(
				Tag('fieldset'+classes,
					Tag('legend.h5',schema.label),
					Tag('div.input-wrapper.hidden-input',Tag('input[name="'+name+'"][type="hidden"]'+datepickerValueAttribute)),
					Tag('div.input-wrapper.ignored',Tag('input#'+name+'[type="text"]'+hiddenValueAttribute ))
				).toString()
			);

			$('#'+name).datepicker( mergedOptions ).on('changeDate',function(e){
				$('#datasource-options-container input[name="'+name+'"]').val( moment(e.date).format('YYYY-MM-DD') ).trigger('change');
			});

			$('#'+name).datepicker('setDate',initValue.toDate());

			dynaformOptions.fields[name] = dynaformFieldOptions;

			return dynaformFieldOptions;
		}

		function build_daterange_component( id, schema, dynaformOptions, container ){
			var
			$c = (function(){
				var
				transforms = {
					days: {
						format:			'mm/dd/yyyy',
						maxViewMode:	'years',
						minViewMode:	'days'
					},
					months: {
						format:			'MM yyyy',
						maxViewMode:	'years',
						minViewMode:	'months'
					},
					years: {
						format:			'yyyy',
						maxViewMode:	'years',
						minViewMode:	'years'
					}
				},
				startName = $.type(schema.start_date.parameter)==='undefined' ? 'start_date' : schema.start_date.parameter,
				endName = $.type(schema.end_date.parameter)==='undefined' ? 'end_date' : schema.end_date.parameter,
				startValue = $.type(schema.start_date.value)!=='undefined' ? moment(schema.start_date.value,'YYYY-MM-DD') : moment(),
				endValue = $.type(schema.end_date.value)!=='undefined' ? moment(schema.end_date.value,'YYYY-MM-DD') : moment(),
				viewMode = $.type(schema.options.startView)==='undefined' ? 'days' : ($.type(transforms[schema.options.startView])==='object' ? schema.options.startView : 'days'),
				startView = viewMode,
				format = transforms[viewMode].format,
				maxViewMode = transforms[viewMode].maxViewMode,
				minViewMode = transforms[viewMode].minViewMode,
				absoluteEarliestMoment = $.type(schema.options.startDate)==='undefined' ? moment('2011-01-01','YYYY-MM-DD') : moment(schema.options.startDate,'YYYY-MM-DD'),
				absoluteLatestMoment = $.type(schema.options.endDate)==='undefined' ? moment() : moment(schema.options.endDate,'YYYY-MM-DD'),
				startEarlyLimit = moment(absoluteEarliestMoment),
				startLateLimit = endValue,
				endEarlyLimit = startValue,
				endLateLimit = moment(absoluteLatestMoment),
				startInput = $( Tag('input[name="'+startName+'"][type="hidden"][value="'+startValue.format('YYYY-MM-DD')+'"]').toString() ),
				endInput = $( Tag('input[name="'+endName+'"][type="hidden"][value="'+endValue.format('YYYY-MM-DD')+'"]').toString() ),
				interiorWrapper = $( Tag('div.daterange-picker-wrapper',
					Tag('div.input-wrapper.ignored',Tag('input#'+startName+'.form-control[type="text"][value="'+startValue.format(window.DMSA.fn.datepicker2Moment(format))+'"]')),
					Tag('div.input-decoration',Tag('span.glyphicon.glyphicon-th.icon-calendar')),
					Tag('div.input-wrapper.ignored',Tag('input#'+endName+'.form-control[type="text"][value="'+endValue.format(window.DMSA.fn.datepicker2Moment(format))+'"]'))
				).toString() ),
				classes = $.type(schema.classes)==='array' ? ('.'+schema.classes.join('.')) : '',
				exteriorWrapper = $( Tag('fieldset.daterange-wrapper'+classes,Tag('legend.h5',schema.label) ).toString() ),
				pickerSpecs = {
					format:		{
						enumerable:	true,
						get:		function(){ return transforms[viewMode].format; },
						set:		function(v){ return transforms[viewMode].format = v; }
					},
					minViewMode:	{
						enumerable:	true,
						get:		function(){ return transforms[viewMode].minViewMode; },
						set:		function(v){ return transforms[viewMode].minViewMode = v; }
					},
					maxViewMode:	{
						enumerable:	true,
						get:		function(){ return transforms[viewMode].maxViewMode; },
						set:		function(v){ return transforms[viewMode].maxViewMode = v; }
					},
					startView:		{
						enumerable:	true,
						get:		function(){ return facade.viewMode; },
						set:		function(v){ return startView = v; }
					}
				},
				startPickerOptions = {autoclose:	true},
				endPickerOptions = {autoclose:	true},

				facade = {
					startName:				startName,
					endName:				endName,
					wrapper:				exteriorWrapper,
					destroy:				destroy_datepicker,
					activate:				create_datepicker,
					reactivate:				recreate_datepicker,
					startChangeListener:	true,
					endChangeListener:		true
				},
				datepickersActive = false,
				pickerData;

				exteriorWrapper.append( interiorWrapper );
				exteriorWrapper.append( startInput );
				exteriorWrapper.append( endInput );


				$( container ).append( exteriorWrapper );

				startInput.wrap('<div class="input-wrapper hidden-input" />');
				endInput.wrap('<div class="input-wrapper hidden-input" />');

				Object.defineProperties(startPickerOptions,$.extend(true,{
					startDate:	{
						enumerable:	true,
						get:		function(){ return facade.startEarlyLimit.toDate(); },
						set:		function(v){ return facade.startEarlyLimit = v; }
					},
					endDate:	{
						enumerable:	true,
						get:		function(){ return facade.startLateLimit.toDate(); },
						set:		function(v){ return facade.startLateLimit = v; }
					},
				},pickerSpecs));

				Object.defineProperties(endPickerOptions,$.extend(true,{
					startDate:	{
						enumerable:	true,
						get:		function(){ return facade.endEarlyLimit.toDate(); },
						set:		function(v){ return facade.endEarlyLimit = v; }
					},
					endDate:	{
						enumerable:	true,
						get:		function(){ return facade.endLateLimit.toDate(); },
						set:		function(v){ return facade.endLateLimit = v; }
					}
				},pickerSpecs));

				Object.defineProperties(facade,{
					datepickersActive:		{
						enumerable:		true,
						get:			function(){ return datepickersActive; },
						set:			function(b){ return datepickersActive = !!b; }
					},
					startValue:			{
						enumerable:		true,
						get:			function(){ return startValue; },
						set:			function(v){
							startValue = v;
							facade.startInputValue = startValue;
							if(v==null){
								facade.endEarlyLimit = moment(absoluteEarliestMoment);
							}else{
								facade.endEarlyLimit = moment(startValue).endOf(facade.viewMode.substr(0,-1));

								if( moment.isMoment(facade.endValue) ){
									if( facade.endValue.isBefore(facade.endEarlyLimit) ){
										//console.log(facade.endValue.format('YYYY-MM-DD')+' is before '+facade.endEarlyLimit.format('YYYY-MM-DD'));
										update_startpicker( facade.endEarlyLimit );
									}
								}else{

								}

								if(!facade.datepickersActive){
									facade.startPickerElement.val( startValue.format(window.DMSA.fn.datepicker2Moment(facade.format)) );
								}
							}
						}
					},
					startPickerValue:	{
						enumerable:	true,
						get:	function(){
							return moment(facade.startPickerElement.datepicker('getDate'));
						},
						set:	function(v){
							return datepickersActive
								?	facade.startPickerElement.datepicker('setDate',v.toDate())
								:	facade.startPickerElement.val(v.format(window.DMSA.fn.datepicker2Moment(facade.format)));
						}
					},
					startInputValue:	{
						enumerable:	true,
						get:	function(){
							return startValue==null
								?	null
								:	startValue.format('YYYY-MM-DD');
							},
						set:	function(v){
							//console.log({startInputValue:	v});
							return facade.startInputElement.val(
								v==null
									? null
									: (	moment.isMoment(v)
										?	v.format('YYYY-MM-DD')
										:	moment(v,'YYYY-MM-DD')
									)
							).trigger('change');
						}
					},
					startPickerOptions:	{
						enumerable:		true,
						get:			function(){ return startPickerOptions; },
						set:			function(v){ return $.extend(true,startPickerOptions,v); }
					},
					startPickerElement:	{
						enumerable:		true,
						get:			function(){ return $('#'+startName); },
						set:			function(v){ return false; }
					},
					startInputElement:	{
						enumerable:		true,
						get:			function(){ return startInput; },
						set:			function(v){ return false; }
					},

					endValue:			{
						enumerable:		true,
						get:			function(){ return endValue; },
						set:			function(v){
							endValue = v;
							facade.endInputValue = endValue;
							if(v==null){
								facade.startLateLimit = moment(absoluteLatestMoment);
							}else{
								facade.startLateLimit = moment(endValue).startOf(facade.viewMode.substr(0,-1));

								if( moment.isMoment(facade.startValue) ){
									if( facade.startValue.isAfter(facade.startLateLimit) ){
										//console.log(facade.startValue.format('YYYY-MM-DD')+' is after '+facade.startLateLimit.format('YYYY-MM-DD'));
										update_endpicker( facade.startLateLimit );
									}
								}

								if(!facade.datepickersActive){
									facade.endPickerElement.val( endValue.format(window.DMSA.fn.datepicker2Moment(facade.format)) );
								}
							}
						}
					},
					endPickerValue:		{
						enumerable:	true,
						get:	function(){
							return moment(facade.endPickerElement.datepicker('getDate'));
						},
						set:	function(v){
							return datepickersActive
								?	facade.endPickerElement.datepicker('setDate',v.toDate())
								:	facade.endPickerElement.val(v.format(window.DMSA.fn.datepicker2Moment(facade.format)));
						}
					},
					endInputValue:		{
						enumerable:	true,
						get:	function(){
							return endValue==null
							 	?	null
								:	endValue.format('YYYY-MM-DD');
						},
						set:	function(v){
							//console.log({endInputValue:	v});
							return facade.endInputElement.val(
								v==null
									? null
									: (	moment.isMoment(v)
										?	v.format('YYYY-MM-DD')
										:	moment(v,'YYYY-MM-DD')
									)
							).trigger('change');
						}
					},
					endPickerOptions:	{
						enumerable:		true,
						get:			function(){ return endPickerOptions; },
						set:			function(v){ return $.extend(true,endPickerOptions,v); }
					},
					endPickerElement:	{
						enumerable:		true,
						get:			function(){ return $('#'+endName); },
						set:			function(v){ return false; }
					},
					endInputElement:	{
						enumerable:		true,
						get:			function(){ return endInput; },
						set:			function(v){ return false; }
					},

					startEarlyLimit:	{
						enumerable:		true,
						get:			function(){ return startEarlyLimit; },
						set:			function(v){ startEarlyLimit = v; return datepickersActive ? refresh_startEarlyLimit() : true; }
					},
					startLateLimit:		{
						enumerable:		true,
						get:			function(){ return startLateLimit; },
						set:			function(v){ startLateLimit = v; return datepickersActive ? refresh_startLateLimit() : true; }
					},
					endEarlyLimit:		{
						enumerable:		true,
						get:			function(){ return endEarlyLimit; },
						set:			function(v){ endEarlyLimit = v; return datepickersActive ? refresh_endEarlyLimit() : true; }
					},
					endLateLimit:		{
						enumerable:		true,
						get:			function(){ return endLateLimit; },
						set:			function(v){ endLateLimit = v; return datepickersActive ? refresh_endLateLimit() : true; }
					},


					format:				{
						enumerable:		true,
						get:			function(){ return transforms[viewMode].format; },
						set:			function(){ transforms[viewMode].format = v; }
					},
					minViewMode:		{
						enumerable:		true,
						get:			function(){ return transforms[viewMode].minViewMode; },
						set:			function(v){ transforms[viewMode].minViewMode = v; }
					},
					maxViewMode:		{
						enumerable:		true,
						get:			function(){ return transforms[viewMode].maxViewMode; },
						set:			function(v){ transforms[viewMode].maxViewMode = v; }
					},
					viewMode:			{
						enumerable:		true,
						get:			function(){ return viewMode; },
						set:			function(v){ viewMode = v; return update_view(); }
					}
				});

				function refresh_startEarlyLimit(){
					return $(facade.startPickerElement).datepicker('setStartDate', startEarlyLimit.toDate() );
				}

				function refresh_startLateLimit(){
					return $(facade.startPickerElement).datepicker('setEndDate', startLateLimit.toDate() );
				}

				function refresh_endEarlyLimit(){
					return $(facade.endPickerElement).datepicker('setStartDate', endEarlyLimit.toDate() );
				}

				function refresh_endLateLimit(){
					return $(facade.endPickerElement).datepicker('setEndDate', endLateLimit.toDate() );
				}

				function update_startpicker( moment ){
					facade.startChangeListener = false;
					facade.startPickerElement.datepicker('setDate',moment.toDate());
					facade.startChangeListener = true;
				}

				function update_endpicker( moment ){
					facade.endChangeListener = false;
					facade.endPickerElement.datepicker('setDate',moment.toDate());
					facade.endChangeListener = true;
				}

				function destroy_datepicker(){
					var
					oldStartPicker = $( facade.startPickerElement ).datepicker('destroy'),
					oldEndPicker = $( facade.endPickerElement ).datepicker('destroy'),
					newStartPicker = oldStartPicker.removeAttr('id').clone(false),
					newEndPicker = oldEndPicker.removeAttr('id').clone(false);

					oldStartPicker.replaceWith( newStartPicker ).datepicker('remove');
					oldEndPicker.replaceWith( newEndPicker ).datepicker('remove');

					newStartPicker.attr('id',startName);
					newEndPicker.attr('id',endName);

					datepickersActive = false;
				}

				function create_datepicker(){
					datepickersActive = true;
					$(facade.startPickerElement).datepicker(facade.startPickerOptions).on({
						'clearDate':	function(e){
							facade.startValue = null;						},
						'changeDate': 	function(e){
							if(facade.startChangeListener){
								facade.startValue = moment(e.date);
							}
						}
					});

					$(facade.endPickerElement).datepicker(facade.endPickerOptions).on({
						'clearDate':	function(e){
							facade.endValue = null;
						},
						'changeDate':	function(e){
							if(facade.endChangeListener){
								facade.endValue = moment(e.date);
							}
						}
					});
				}

				function recreate_datepicker(){
					var
					originalStartDate = startValue.format('YYYY-MM-DD'),
					originalEndDate = endValue.format('YYYY-MM-DD'),
					originalStartPickerValue = facade.startPickerValue,
					originalEndPickerValue = facade.endPickerValue,
					newStartMoment = moment(originalStartDate,'YYYY-MM-DD').startOf( facade.viewMode ),
					newEndMoment = moment(originalEndDate,'YYYY-MM-DD').endOf( facade.viewMode ),
					newStartDate = newStartMoment.format('YYYY-MM-DD'),
					newEndDate = newEndMoment.format('YYYY-MM-DD');

					destroy_datepicker();

					// console.log({
					// 	period:						viewMode,
					// 	originalStartDate:			originalStartDate,
					// 	originalEndDate:			originalEndDate,
					// 	newStartMoment:				newStartMoment,
					// 	newEndMoment:				newEndMoment,
					// 	newStartDate:				newStartDate,
					// 	newEndDate:					newEndDate,
					// 	originalStartPickerValue:	originalStartPickerValue,
					// 	originalEndPickerValue:		originalEndPickerValue,
					// 	facade:						facade
					// });

					facade.startValue = newStartMoment;
					facade.endValue = newEndMoment;
				}

				function update_view(){
					var
					originalStartDate = startValue.format('YYYY-MM-DD'),
					originalEndDate = endValue.format('YYYY-MM-DD'),
					originalStartPickerValue = facade.startPickerValue,
					originalEndPickerValue = facade.endPickerValue,
					newStartMoment = moment(originalStartDate,'YYYY-MM-DD').startOf( facade.viewMode ),
					newEndMoment = moment(originalEndDate,'YYYY-MM-DD').endOf( facade.viewMode ),
					newStartDate = newStartMoment.format('YYYY-MM-DD'),
					newEndDate = newEndMoment.format('YYYY-MM-DD'),
					datepickersActive = false;

					// console.log({
					// 	originalStartDate:			originalStartDate,
					// 	originalEndDate:			originalEndDate,
					// 	newStartMoment:				newStartMoment,
					// 	newEndMoment:				newEndMoment,
					// 	newStartDate:				newStartDate,
					// 	newEndDate:					newEndDate,
					// 	originalStartPickerValue:	originalStartPickerValue,
					// 	originalEndPickerValue:		originalEndPickerValue,
					// 	facade:						facade
					// });

					destroy_datepicker();

					facade.startValue = newStartMoment;
					facade.endValue = newEndMoment;

					create_datepicker();
				}

				//console.log({facade: facade});

				return facade;

			})(),
			wrapper = $c.wrapper,
			returnedStartObject = {},
			returnedEndObject = {},
			result;

			console.log({$c: $c});

			dynaformOptions.fields[$c.startName] = {
				value: 		$c.startInputValue,
				validate:	validator
			};
			dynaformOptions.fields[$c.endName] = {
				value: $c.endInputValue,
				validate:	validator
			};

			if($.type(schema.validate)!=='undefined'){
				dynaformOptions.fields[$c.startName].validate = schema.validate;
				dynaformOptions.fields[$c.endName].validate = schema.validate;
			}

			if($.type(dynaformOptions.props)==='undefined'){
				dynaformOptions.props = new Object;
			}

			if($.type(dynaformOptions.props[id])==='undefined'){
				dynaformOptions.props[id] = $c;
			}

			returnedStartObject[$c.startName] = dynaformOptions.fields[$c.startName];
			returnedEndObject[$c.endName] = dynaformOptions.fields[$c.endName];

			$.each('onChange,onBlur,onFocus'.split(','),function(i,prop){
				if( $.type(schema[prop])!=='undefined' ){
					dynaformOptions.fields[$c.startName][prop] = new Function(schema[prop].args, $.type(schema[prop].body)==='array'
						?	schema[prop].body.join('\n')
						:	schema[prop].body
					);
					dynaformOptions.fields[$c.endName][prop] = new Function(schema[prop].args, $.type(schema[prop].body)==='array'
						?	schema[prop].body.join('\n')
						:	schema[prop].body
					);
				}else{
					if( $.type(schema.start_date[prop])!=='undefined' ){
						dynaformOptions.fields[$c.startName][prop] = new Function(schema.start_date[prop].args, $.type(schema.start_date[prop].body)==='array'
							?	schema.start_date[prop].body.join('\n')
							:	schema.start_date[prop].body
						);
					}
					if( $.type(schema.end_date[prop])!=='undefined' ){
						dynaformOptions.fields[$c.endName][prop] = new Function(schema.end_date[prop].args, $.type(schema.end_date[prop].body)==='array'
							?	schema.end_date[prop].body.join('\n')
							:	schema.end_date[prop].body
						);
					}
				}
			});

			$c.activate();

			result = [returnedStartObject,returnedEndObject];

			function validator(input){ return /^(19|20)[0-9]{2}\-[0-1][0-9]\-[0-3][0-9]$/.test(input) ? true : 'Daterange required'; }

			return result;
		}

		function datepicker2DMSA( input ){
			return $.type(input)==='string'
				?	moment( input, 'MM/DD/YYYY' ).format('YYYY-MM-DD')
				:	($.type(input)==='object'
					?	(input instanceof moment
						?	input.format('YYYY-MM-DD')
						:	(input instanceof Date
							?	moment( input ).format('YYYY-MM-DD')
							:	null
						)
					)
					:	null
				);
		}

		function DMSA2Datepicker( input ){
			return $.type(input)==='string'
				?	moment( input, 'YYYY-MM-DD' ).format('MM/DD/YYYY')
				:	($.type(input)==='object'
					?	(input instanceof moment
						?	input.format('MM/DD/YYYY')
						:	(input instanceof Date
							?	moment( input ).format('MM/DD/YYYY')
							:	null
						)
					)
					:	null
				);
		}

		function momentToDatepicker( formatString ){
			var tokenTranslation = {
				'YYYY':		'yyyy',
				'YY':		'yy',
				'MMMM':		'MM',
				'MMM':		'M',
				'MM':		'mm',
				'M':		'm',
				'DD':		'dd',
				'D':		'd',
				'dddd':		'DD',
				'ddd':		'D'
			}

			$.each( tokenTranslation, function(m,d){
				var
				mPattern = new RegExp('\\b'+m+'\\b','g');
				if( mPattern.test( formatString ) ){
					formatString = formatString.replace(mPattern,d);
				}
			});

			return formatString;
		}

		function datepickerToMoment( formatString ){
			var tokenTranslation = {
				'yyyy':		'YYYY',
				'yy':		'YY',
				'MM':		'MMMM',
				'M':		'MMM',
				'mm':		'MM',
				'm':		'M',
				'DD':		'dddd',
				'D':		'ddd',
				'dd':		'DD',
				'd':		'D'
			}

			$.each( tokenTranslation, function(m,d){
				var
				mPattern = new RegExp('\\b'+m+'\\b','g');
				if( mPattern.test( formatString ) ){
					formatString = formatString.replace(mPattern,d);
				}
			});

			return formatString;
		}

		function populate_available_reports_dropdown( data ){
			$.each( data, function(i,e){
				$reports[e.id] = e;
				$('#report-selector').append(
					Tag('option[value="'+e.id+'"]',e.title).toString()
				);
			});

			$('#report-selector').select2({
				placeholder: 	'Select Report',
				closeOnSelect: 	true
			});
		}

		function Workbook( workbookElement ){
			var
			data,
			v = 0,
			workbookObj = this,
			spreadsheets = [],
			tabSelector = workbookElement.find('#workbook-tabs > ul'),
			tabContent = workbookElement.find('#workbook-content');

			this.tabs_ul =  tabSelector;
			this.tabs_div = tabContent;
			this.spreadsheets = spreadsheets;

			this.update_report_preview = update_report_preview;
			function update_report_preview( workbookData ){
				clear_spreadsheets(function(){
					$.each( workbookData, function(sheetId,sheetSchema){
						var
						spreadsheet = new Spreadsheet( sheetSchema, workbookObj, spreadsheets.length );
						spreadsheets.push( spreadsheet );
					});
				});
			}

			this.clear_spreadsheets = clear_spreadsheets;
			function clear_spreadsheets( callbackFn ){
				while( spreadsheets.length ){
					spreadsheets.shift().tear_down();
				}
				tabSelector.empty();
				tabContent.empty();
				if($.type(callbackFn)==='function'){
					callbackFn();
				}
			}

			this.drop_spreadsheet = drop_spreadsheet;
			function drop_spreadsheet( sheet_id ){
				var
				sheet_obj = select_spreadsheet_by_sheet_id( sheet_id ),
				sheet_idx = spreadsheets.indexOf(sheet_obj),
				was_active = sheet_obj.is_active();

				sheet_obj.selector.addClass('dropping');
				sheet_obj.interior.addClass('dropping');

				// if(was_active){
				// 	switch( true )
				// 	{
				// 		case $.type(spreadsheets[sheet_idx-1])==='object':
				// 			spreadsheets[sheet_idx+1].activate();
				// 		break;
				//
				// 		case $.type(spreadsheets[sheet_idx+1])==='object':
				// 			spreadsheets[sheet_idx+1].activate();
				// 		break;
				// 	}
				// }

				setTimeout(function(){
					sheet_obj.tear_down();
				},500);

				//console.log({was_active: was_active});
			}

			this.update_spreadsheet = update_spreadsheet;
			function update_spreadsheet( sheet_id, data ){
				var sheet_obj = select_spreadsheet_by_sheet_id( sheet_id );
				sheet_obj.update_contents( data );
			}

			function select_spreadsheet_by_sheet_id( sheet_id ){
				var sheet;
				$.each(spreadsheets,function(sheetIdx,sheetObj){
					if(sheetObj.id==sheet_id){
						sheet = sheetObj;
						return false;
					}
				});
				return sheet;
			}
		}

		function Spreadsheet( schema, workbookObj, sheetIdx ){
			var
			id = schema.id,
			elem_id = 'sstab_'+id,
			activateClass = sheetIdx===0 ? '.active' : '',
			title = $.type(schema.title)==='string' ? schema.title : 'Untitled',
			interior = $(Tag('div#'+elem_id+'.tab-pane'+activateClass+'[data-sheet-id="'+id+'"]').toString()),
			anchorEle = Tag('a[href="#'+elem_id+'"][data-toggle="tab"]',Tag('span',title)),
			selectorEle = Tag('li'+activateClass+'[data-sheet-id="'+id+'"]', anchorEle ),
			contents = [],
			tables = [],
			selector,
			$this = this;

			this.id = id;
			this.elem_id = elem_id;
			this.contents = contents;

			if($.type(schema.buttons)==='object'){
				$.each(schema.buttons,function(btnName,markup){
					var buttonMarkup = Markup( markup )
					anchorEle.prepend( buttonMarkup.toString() );
				});
			}

			selector = $( selectorEle.toString() );

			workbookObj.tabs_ul.append( selector );
			workbookObj.tabs_div.append( interior );

			this.interior = interior;
			this.selector = selector;
			this.tables = tables;

			init_contents( schema.contents );

			this.is_active = is_active;
			function is_active(){ return selector.hasClass('active'); }

			this.activate = activate;
			function activate(){
				//console.log({'activated!': this.id});
				selector.addClass('active');
				interior.addClass('active');
			}

			this.tear_down = tear_down;
			function tear_down(){
				//console.log('this.tear_down');
				var
				active = is_active(),
				takeMyPlace;
				workbookObj.spreadsheets.splice( sheetIdx, 1 );
				$.each( tables, function(idx,ele){
					ele.destroy();
				});
				interior.remove();
				selector.remove();
			}

			this.add_table = add_table;
			function add_table( tableSchema, idx ){
				var tableType = $.type(tableSchema.type)==='string'
					?	tableSchema.type
					:	'basic';

				switch( tableType )
				{
					case 'breakdown':
						new BreakdownTable( tableSchema, idx, $this );
					break;

					case 'rowgrouped':
						new RowgroupedTable( tableSchema, idx, $this );
					break;

					default:
						new BasicTable( tableSchema, idx, $this );
					break;
				}
			}

			function add_irregular_table( tableSchema, idx ){
				var
				dtable,
				close = Tag('button.close[data-dmsa-fn="remove_report_entity"]',Tag('span','×')),
				cols = tableSchema.thead.total_columns,
				thead = Tag('thead'),
				tbody = Tag('tbody'),
				tSelector = 'table.dataTable[data-table-index="'+String(idx)+'"]',
				table = Tag(tSelector,thead,tbody),
				container = Tag('div.box.entity-wrapper',Tag('div.dataTables_wrapper',table),close),
				ddata = [],
				dtableColumns = [],
				formats = {},
				types = {},
				dtableOptions = {
					dom:			't',
					autoWidth:		false,
					paging:			false,
					responsive:		false,
					searching:		false,
					ordering:		false,
					info:			false,
					data:			[]
				};


				if($.type(tableSchema.title)==='string'){
					container.prepend( Tag('h5.left-padding.text-left-aligned',tableSchema.title) );
				}

				$.each(tableSchema.thead.rows, function(hRowIdx,hRowArray){
					var
					hRow = Tag('tr');
					$.each(hRowArray,function(hColIdx,hColTitle){
						hRow.append( Tag('th.sorting_disabled'+(tableSchema.thead.colspans[hRowIdx][hColIdx] > 1
							?	'[colspan="'+String(tableSchema.thead.colspans[hRowIdx][hColIdx])+'"]'
							:	''),hColTitle) );
					});
					thead.append( hRow );
				});

				$.each(tableSchema.tbody.rows, function(rowIndex,rowData){
					var
					row = Tag('tr'+(rowIndex % 2 === 0 ? '' : '.odd')+'[data-row-index="'+String(rowIndex)+'"]'),
					rowType = $.type(rowData['@type'])==='string' ? rowData['@type'] : 'data';
					if(rowType==='data'){
						$.each(tableSchema.row_map,function(colIdx,colId){
							var
							rawValue = $.type(tableSchema.columnIndex[colId].row_values[rowIndex])!=='undefined'
								?	tableSchema.columnIndex[colId].row_values[rowIndex]
								:	'',
							formatType = $.type(tableSchema.columnIndex[colId].format),
							cellFormat = formatType==='string'
								?	tableSchema.columnIndex[colId].format
								:	(formatType==='array'||formatType==='object'
									?	($.type(tableSchema.columnIndex[colId].format[rowIndex])==='string'
										?	tableSchema.columnIndex[colId].format[rowIndex]
										:	false)
									:	false
								),
							cellValue = cellFormat!==false && $.type(FW.fn[cellFormat])==='function'
								?	FW.fn[cellFormat](rawValue)
								:	rawValue;
							row.append(
								Tag('td', $.type(tableSchema.columnIndex[colId].row_values[rowIndex])!=='undefined'
									?	cellValue
									:	null
								)
							);
						});
					}else{
						if($.type(rowData['@label'])==='string'){
							row.append(
								Tag('td.divider-row[colspan="'+String(tableSchema.thead.total_columns)+'"]',
								rowData['@label'])
							);
						}
					}
					tbody.append( row );
				});

				interior.append( container.toString() );

				tables.push({
					destroy: function(){/*noop*/}
				});
				// dtable = interior.find('[data-table-index="'+String(idx)+'"]').dataTable(dtableOptions);
				// tables.push( dtable );
			}

			function add_basic_table( tableSchema, idx ){
				var
				dtable,
				close = Tag('button.close[data-dmsa-fn="remove_report_entity"]',Tag('span','×')),
				cols = tableSchema.thead.total_columns,
				thead = Tag('thead'),
				tfoot = Tag('tfoot'), footRow,
				table = Tag('table[data-table-index="'+String(idx)+'"]',thead,tfoot),
				container = Tag('div.box.entity-wrapper',table,close),
				ddata = [],
				dtableColumns = [],
				formats = {},
				types = {},
				dtableOptions = {
					dom:			't',
					autoWidth:		false,
					paging:			false,
					responsive:		false,
					searching:		false,
					ordering:		false,
					info:			false
				};

				if($.type(tableSchema.title)==='string'){
					container.prepend( Tag('h5.text-centered',tableSchema.title) );
				}

				$.each(tableSchema.thead.rows, function(hRowIdx,hRowArray){
					var
					hRow = Tag('tr');
					$.each(hRowArray,function(hColIdx,hColTitle){
						hRow.append( Tag('th'+(tableSchema.thead.colspans[hRowIdx][hColIdx] > 1
							?	'[colspan="'+String(tableSchema.thead.colspans[hRowIdx][hColIdx])+'"]'
							:	''),hColTitle) );
					});
					thead.append( hRow );
				});

				$.each(tableSchema.row_map, function(colIdx,colId){
					var
					dTableColDef = new Object,
					colSchema = tableSchema.columnIndex[colId],
					valueGetters = $.type(colSchema.getters)==='object' && $.type(colSchema.getters.value)==='object'
						?	colSchema.getters.value
						:	{},
					vType = $.type(colSchema.type)==='string'
						?	colSchema.type
						:	($.type(valueGetters.type)==='string'
							?	valueGetters.type
							:	'string'),
					vSortable = $.type(colSchema.sortable)!=='undefined'
						?	!!colSchema.sortable
						:	($.type(valueGetters.sortable)!=='undefined'
							?	!!valueGetters.sortable
							:	true),
					vRender = $.type(colSchema.format)==='string'
						?	colSchema.format
						:	($.type(valueGetters.format)==='string'
							?	($.type(FW.fn[valueGetters.format])==='function'
								?	FW.fn[valueGetters.format]
								:	null)
							:	null);

					dTableColDef.data = colId.replace('.','__');
					dTableColDef.type = vType;
					dTableColDef.sortable = vSortable;

					types[colId] = vType;

					if(vRender!=null){
						dTableColDef.render = vRender;
						formats[colId] = vRender;
					}

					dtableColumns.push( dTableColDef );
				});

				if( $.type(tableSchema.tfoot)==='object' ){
					footRow = Tag('tr');
					$.each(tableSchema.tfoot.row_values,function(fColId,fColValue){
						footRow.append(Tag('td[data-field="'+fColId+'"]',
							$.type(formats[fColId])=='function'
								?	formats[fColId](fColValue)
								:	fColValue
						));
					});
					tfoot.append(footRow);
				}

				interior.append( container.toString() );

				$.each(tableSchema.tbody.rows,function(rowIdx,rowValues){
					var temp = {};
					$.each(rowValues,function(key,val){
						var
						newKey = key.replace('.','__');
						temp[newKey] = val;
					});
					ddata.push(temp);
				});

				dtableOptions.data = ddata;
				dtableOptions.columns = dtableColumns;

				dtable = interior.find('[data-table-index="'+String(idx)+'"]').dmsaTable(dtableOptions);
				tables.push( dtable );
			}

			this.add_text = add_text;
			function add_text( objSchema, idx ){
				interior.append( Tag('div.entity-wrapper', Tag('p',objSchema.content) ).toString(), Tag('span','×') );
			}

			function init_contents( conts ){
				$.each( conts, function( idx, obj ){
					switch( obj.type )
					{
						case 'table':
							add_table( obj.schema, idx );
							//var tableType = $.type(obj.schema.type)==='string'
							//	?	obj.schema.type
							//	:	'basic';


							//tableType==='breakdown'
							//	?	new BreakdownTable( obj.schema, idx, $this )
							//	:	new BasicTable( obj.schema, idx, $this );
						break;

						case 'text':
							new TextContent( obj.schema, idx, $this );
						break;
					}
				});
			}

			this.update_contents = update_contents;
			function update_contents( newSchema ){
				tables.splice(0,tables.length);
				while(contents.length > 0){
					contents.shift().drop();
				}
				init_contents( newSchema.contents );
			}

			function TextContent( contentSchema, idx, sheetObj ){
				var
				containerElem = $(Tag('div.entity-wrapper',Tag('p',contentSchema.content)).toString());

				this.idx = idx;

				sheetObj.interior.append( containerElem );

				this.drop = function(){
					containerElem.remove();
				}
			}

			function RowgroupedTable( tableSchema, idx, sheetObj ){
				var
				dtable,
				close = Tag('button.close[data-dmsa-fn="remove_report_entity"]',Tag('span','×')),
				cols = tableSchema.thead.total_columns,
				theadEle = Tag('thead'),
				tbodyEle = Tag('tbody'),
				thead = theadEle,
				tbody = tbodyEle,
				tSelector = 'table.dataTable.row-grouping[data-table-index="'+String(idx)+'"]',
				table = Tag(tSelector,theadEle,tbodyEle),
				//container = Tag('div.box.entity-wrapper',Tag('div.dataTables_wrapper',table),close),
				container = Tag('div.box.entity-wrapper',Tag('div.dataTables_wrapper',table)),
				groupedColumns = get_grouped_column_ids(tableSchema.rowGroupings),
				ddata = [],
				dtableColumns = [],
				formats = {},
				types = {},
				dtableOptions = {
					dom:			't',
					autoWidth:		false,
					paging:			false,
					responsive:		false,
					searching:		false,
					ordering:		false,
					info:			false,
					data:			[]
				},
				containerElem,
				$this = this;

				this.idx = idx;
				this.build_data_row = build_data_row;
				this.build_header_row = build_header_row;
				this.build_divider_row = build_divider_row;


				if($.type(tableSchema.title)==='string'){
					container.prepend( Tag('h5.left-padding.text-left-aligned',tableSchema.title) );
				}

				$.each(tableSchema.thead.rows, function(hRowIdx,hRowArray){
					thead.append( $this.build_header_row(hRowIdx,hRowArray) );
				});

				$.each(tableSchema.tbody.rows, function(rowIndex,rowData){
					var
					rowType = $.type(rowData['@type'])==='string' ? rowData['@type'] : 'data',
					buildMethod = 'build_'+rowType+'_row';

					if(rowType==='headers'){
						var
						anotherThead = Tag('thead'),
						anotherTbody = Tag('tbody');

						thead = anotherThead;
						tbody = anotherTbody;

						table.append(anotherThead,anotherTbody);

						$.each(tableSchema.thead.rows, function( hRowIdx, hRowArray ){
							thead.append( $this.build_header_row( hRowIdx, hRowArray ) );
						});
					}else{
						tbody.append( $this[buildMethod](rowIndex,rowData) );
					}
				});



				containerElem = $( container.toString() );

				sheetObj.interior.append( containerElem );

				sheetObj.contents.push( this );
				sheetObj.tables.push( this );

				this.destroy = function(){ /* noop */ }

				this.drop = function(){
					dtable.destroy();
					containerElem.remove();
				}

				function get_grouped_column_ids( groupings ){
					var output = [];
					$.each(groupings,function(i,e){
						var these = $.type(e)==='array'
							?	get_grouped_column_ids(e)
							:	[e];
						$.each(these,function(ii,ee){
							output.push(ee);
						});
					});
					return output;
				}

				function build_data_row(rowIndex,rowData){
					var
					row = Tag('tr'+(rowIndex % 2 === 0 ? '' : '.odd')+'[data-row-index="'+String(rowIndex)+'"]');
					$.each(tableSchema.row_map,function(colIdx,colId){
						var
						rawValue = $.type(tableSchema.columnIndex[colId].row_values[rowIndex])!=='undefined'
							?	tableSchema.columnIndex[colId].row_values[rowIndex]
							:	'',
						formatSpec = seek_property(
							tableSchema.columnIndex[colId],
							[
								'format',
								'value.format',
								'getters.format',
								'getters.value.format'
							],
							null
						),
						formatType = $.type(formatSpec),
						cellFormat = formatType==='string'
							?	formatSpec
							:	(formatType==='array'||formatType==='object'
								?	($.type(formatSpec[rowIndex])==='string'
									?	formatSpec[rowIndex]
									:	false)
								:	false
							),
						cellValue = cellFormat!==false && $.type(FW.fn[cellFormat])==='function'
							?	FW.fn[cellFormat](rawValue)
							:	rawValue,

						cellTag = groupedColumns.indexOf(colId)===-1
							?	Tag('td', cellValue)
							:	(	$.type(tableSchema.columnIndex[colId].row_spans[rowIndex])==='undefined'
								?	false
								:	Tag('td[rowspan="'+String(tableSchema.columnIndex[colId].row_spans[rowIndex])+'"]', rawValue));


						if(cellTag!==false){
							row.append( cellTag );
						}
					});
					return row;
				}

				function build_divider_row(rowIndex,rowData){
					var
					row = Tag('tr'+(rowIndex % 2 === 0 ? '' : '.odd')+'[data-row-index="'+String(rowIndex)+'"]');
					if($.type(rowData['@label'])==='string'){
						row.append(
							Tag('td.divider-row[colspan="'+String(tableSchema.thead.total_columns)+'"]',
							rowData['@label'])
						);
					}
					return row;
				}

				function build_header_row(hRowIdx,hRowArray){
					var
					hRow = Tag('tr');
					$.each(hRowArray,function(hColIdx,hColTitle){
						hRow.append( Tag('th.sorting_disabled'+(tableSchema.thead.colspans[hRowIdx][hColIdx] > 1
							?	'[colspan="'+String(tableSchema.thead.colspans[hRowIdx][hColIdx])+'"]'
							:	''),hColTitle) );
					});
					return hRow;
				}
			}

			function BasicTable( tableSchema, idx, sheetObj ){
				var
				dtable,
				close = Tag('button.close[data-dmsa-fn="remove_report_entity"]',Tag('span','×')),
				cols = tableSchema.thead.total_columns,
				thead = Tag('thead'),
				tfoot = Tag('tfoot'), footRow,
				table = Tag('table[data-table-index="'+String(idx)+'"]',thead,tfoot),
				//container = Tag('div.box.entity-wrapper',table,close),
				container = Tag('div.box.entity-wrapper',table),
				ddata = [],
				dtableColumns = [],
				formats = {},
				types = {},
				dtableOptions = {
					dom:			't',
					autoWidth:		false,
					paging:			false,
					responsive:		false,
					searching:		false,
					ordering:		false,
					info:			false
				},
				containerElem;

				this.idx = idx;

				if($.type(tableSchema.title)==='string'){
					container.prepend( Tag('h5.text-centered',tableSchema.title) );
				}

				$.each(tableSchema.thead.rows, function(hRowIdx,hRowArray){
					var
					hRow = Tag('tr');
					$.each(hRowArray,function(hColIdx,hColTitle){
						hRow.append( Tag('th'+(tableSchema.thead.colspans[hRowIdx][hColIdx] > 1
							?	'[colspan="'+String(tableSchema.thead.colspans[hRowIdx][hColIdx])+'"]'
							:	''),hColTitle) );
					});
					thead.append( hRow );
				});

				$.each(tableSchema.row_map, function(colIdx,colId){
					var
					dTableColDef = new Object,
					colSchema = tableSchema.columnIndex[colId],
					valueGetters = $.type(colSchema.getters)==='object' && $.type(colSchema.getters.value)==='object'
						?	colSchema.getters.value
						:	{},
					vType = $.type(colSchema.type)==='string'
						?	colSchema.type
						:	($.type(valueGetters.type)==='string'
							?	valueGetters.type
							:	'string'),
					vSortable = $.type(colSchema.sortable)!=='undefined'
						?	!!colSchema.sortable
						:	($.type(valueGetters.sortable)!=='undefined'
							?	!!valueGetters.sortable
							:	true),
					vRender = $.type(colSchema.format)==='string'
						?	colSchema.format
						:	($.type(valueGetters.format)==='string'
							?	($.type(FW.fn[valueGetters.format])==='function'
								?	FW.fn[valueGetters.format]
								:	null)
							:	null);

					dTableColDef.data = colId.replace('.','__');
					dTableColDef.type = vType;
					dTableColDef.sortable = vSortable;

					types[colId] = vType;

					if(vRender!=null){
						dTableColDef.render = vRender;
						formats[colId] = vRender;
					}

					dtableColumns.push( dTableColDef );
				});

				if( $.type(tableSchema.tfoot)==='object' ){
					footRow = Tag('tr');
					$.each(tableSchema.tfoot.row_values,function(fColId,fColValue){
						footRow.append(Tag('td[data-field="'+fColId+'"]',
							$.type(formats[fColId])=='function'
								?	formats[fColId](fColValue)
								:	fColValue
						));
					});
					tfoot.append(footRow);
				}

				containerElem = $( container.toString() );

				sheetObj.interior.append( containerElem );

				$.each(tableSchema.tbody.rows,function(rowIdx,rowValues){
					var temp = {};
					$.each(rowValues,function(key,val){
						var
						newKey = key.replace('.','__');
						temp[newKey] = val;
					});
					ddata.push(temp);
				});

				dtableOptions.data = ddata;
				dtableOptions.columns = dtableColumns;

				dtable = sheetObj.interior.find('[data-table-index="'+String(idx)+'"]').dmsaTable(dtableOptions);

				this.destroy = dtable.destroy;

				sheetObj.contents.push( this );
				sheetObj.tables.push( this );

				this.drop = function(){
					dtable.destroy();
					containerElem.remove();
				}
			}

			function BreakdownTable( tableSchema, idx, sheetObj ){
				var
				dtable,
				close = Tag('button.close[data-dmsa-fn="remove_report_entity"]',Tag('span','×')),
				cols = tableSchema.thead.total_columns,
				theadEle = Tag('thead'),
				tbodyEle = Tag('tbody'),
				thead = theadEle,
				tbody = tbodyEle,
				tSelector = 'table.dataTable[data-table-index="'+String(idx)+'"]',
				table = Tag(tSelector,theadEle,tbodyEle),
				container = Tag('div.box.entity-wrapper',Tag('div.dataTables_wrapper',table)),
				ddata = [],
				dtableColumns = [],
				formats = {},
				types = {},
				dtableOptions = {
					dom:			't',
					autoWidth:		false,
					paging:			false,
					responsive:		false,
					searching:		false,
					ordering:		false,
					info:			false,
					data:			[]
				},
				containerElem,
				$this = this;

				this.idx = idx;
				this.build_data_row = build_data_row;
				this.build_header_row = build_header_row;
				this.build_divider_row = build_divider_row;


				if($.type(tableSchema.title)==='string'){
					container.prepend( Tag('h5.left-padding.text-left-aligned',tableSchema.title) );
				}

				$.each(tableSchema.thead.rows, function(hRowIdx,hRowArray){
					thead.append( $this.build_header_row(hRowIdx,hRowArray) );
				});

				$.each(tableSchema.tbody.rows, function(rowIndex,rowData){
					var
					rowType = $.type(rowData['@type'])==='string' ? rowData['@type'] : 'data',
					buildMethod = 'build_'+rowType+'_row';

					if(rowType==='headers'){
						var
						anotherThead = Tag('thead'),
						anotherTbody = Tag('tbody');

						thead = anotherThead;
						tbody = anotherTbody;

						table.append(anotherThead,anotherTbody);

						$.each(tableSchema.thead.rows, function( hRowIdx, hRowArray ){
							thead.append( $this.build_header_row( hRowIdx, hRowArray ) );
						});
					}else{
						tbody.append( $this[buildMethod](rowIndex,rowData) );
					}
				});

				containerElem = $( container.toString() );

				sheetObj.interior.append( containerElem );

				sheetObj.contents.push( this );
				sheetObj.tables.push( this );

				this.destroy = function(){ /* noop */ }

				this.drop = function(){
					dtable.destroy();
					containerElem.remove();
				}

				function build_data_row(rowIndex,rowData){
					var
					row = Tag('tr'+(rowIndex % 2 === 0 ? '' : '.odd')+'[data-row-index="'+String(rowIndex)+'"]');
					$.each(tableSchema.row_map,function(colIdx,colId){
						var
						rawValue = $.type(tableSchema.columnIndex[colId].row_values[rowIndex])!=='undefined'
							?	tableSchema.columnIndex[colId].row_values[rowIndex]
							:	'',
						formatType = $.type(tableSchema.columnIndex[colId].format),
						cellFormat = formatType==='string'
							?	tableSchema.columnIndex[colId].format
							:	(formatType==='array'||formatType==='object'
								?	($.type(tableSchema.columnIndex[colId].format[rowIndex])==='string'
									?	tableSchema.columnIndex[colId].format[rowIndex]
									:	false)
								:	false
							),
						cellValue = cellFormat!==false && $.type(FW.fn[cellFormat])==='function'
							?	FW.fn[cellFormat](rawValue)
							:	rawValue;
						row.append(
							Tag('td', $.type(tableSchema.columnIndex[colId].row_values[rowIndex])!=='undefined'
								?	cellValue
								:	null
							)
						);
					});
					return row;
				}

				function build_divider_row(rowIndex,rowData){
					var
					row = Tag('tr'+(rowIndex % 2 === 0 ? '' : '.odd')+'[data-row-index="'+String(rowIndex)+'"]');
					if($.type(rowData['@label'])==='string'){
						row.append(
							Tag('td.divider-row[colspan="'+String(tableSchema.thead.total_columns)+'"]',
							rowData['@label'])
						);
					}
					return row;
				}

				function build_header_row(hRowIdx,hRowArray){
					var
					hRow = Tag('tr');
					$.each(hRowArray,function(hColIdx,hColTitle){
						hRow.append( Tag('th.sorting_disabled'+(tableSchema.thead.colspans[hRowIdx][hColIdx] > 1
							?	'[colspan="'+String(tableSchema.thead.colspans[hRowIdx][hColIdx])+'"]'
							:	''),hColTitle) );
					});
					return hRow;
				}

			}
		}
	});
})(jQuery);
