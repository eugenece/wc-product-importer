<?php 

function tfc_check_APIs($tfcOptions){
	$output = false;
	$msStatus = false;
	$siteStatus = false;
	$siteUrl = site_url();
	
	$options = array(
		'debug'           => true,
		'return_as_array' => false,
		'validate_url'    => false,
		'timeout'         => 30,
		'ssl_verify'      => false,
	);
	
	if(is_array($tfcOptions) && !empty($tfcOptions) && !in_array('',$tfcOptions)):
		require_once( TFC_DIR.'/lib/woocommerce-api.php' );
		try{
			$clientMS = new WC_API_Client( 
				tfc_get_opttion($tfcOptions,'ms','site_url'), 
				tfc_get_opttion($tfcOptions,'ms','consumer_key'), 
				tfc_get_opttion($tfcOptions,'ms','secret_key'), 
				$options 
			);
			
			$msProduct = $clientMS->products->get();
			$msStatus = true;
		}catch ( WC_API_Client_Exception $e ) {
			$msStatus = false;
		}
		
		if($msStatus == true) {
			try{
				$clientTFC = new WC_API_Client( 
					$siteUrl,
					tfc_get_opttion($tfcOptions,'site','consumer_key'), 
					tfc_get_opttion($tfcOptions,'site','secret_key'), 
					$options 
				);
				$siteProduct = $clientTFC->products->get();
				$siteStatus = true;
			}catch ( WC_API_Client_Exception $e ) {
				$siteStatus = false;
			}
		}
		
		
		if($msStatus == true && $siteStatus == true):
			$output = true;
		else:
			$output = false;
		endif;
	endif;
	
	return $output;
}


function tfc_get_terms_ids($terms,$taxonomy){
	$output = array();
	
	if(!empty($terms) && is_array($terms)):
		foreach($terms as $term):
			$theTerm = get_term_by('name', $term, $taxonomy);
			array_push($output,$theTerm->term_id);
		endforeach;
	endif;
	
	return $output;
}

//Change format of a URL
function tfc_change_url($url){
	$output = "";
	$siteUrl = site_url();
	
	if(isset($url)):
		$array = parse_url($url);
		$path = $array['path'];
		//$query = (!empty($array['query']) ? '?'.$array['query'] : '');
		
		//$output = $siteUrl.$path.$query;
		$output = $url;
	endif;
	
	return $output;
}

//Print the Memory Usage
function tfc_get_memory_usage() { 
	$output = "";
    $mem_usage = memory_get_usage(true); 
    
    if ($mem_usage < 1024): 
        $output .= $mem_usage." bytes"; 
    elseif ($mem_usage < 1048576):
        $output .= round($mem_usage/1024,2)." kilobytes"; 
    else: 
        $output .= round($mem_usage/1048576,2)." megabytes"; 
    endif;
    return $output;
}

//Recreate attributes
function tfc_recreate_attributes($variations){
	$output = array();
	
	$newAttribs = array();
	foreach($variations as $attribName):
		foreach($attribName['attributes'] as $AttName):
			$theName = $AttName['slug'];
			if(!in_array($theName,$newAttribs)):
				$newAttribs[$theName]['slug'] = $theName;
			endif;
			if(!isset($newAttribs[$theName]['options'])) $newAttribs[$theName]['options'] = array();
			
			array_push($newAttribs[$theName]['options'],$AttName['option']);
		endforeach;
	endforeach;
	
	foreach($newAttribs as $key => $newAttrib):
		
		$theAttributes = array(
			'name'=>$key,
			'slug'=>$newAttrib['slug'],
			'position'=>'0',
			'visible'=>true,
			'options' => $newAttrib['options'],
			'variation'=>true
		);
		array_push($output,$theAttributes);
	endforeach;
	
	return $output;
}

function tfc_update_variations($client,$array){
	if(is_array($array) && !empty($array)):
		foreach($array as $variation):
		// Update Variation Prices
		$client->products->update($variation['id'],
			array(
				'regular_price'=> $variation['price'],
				'virtual'=>false,
				'attributes' => $variation['attributes']
			));
		endforeach;
	endif;
}

function tfc_check_variation($attrib,$vars,$price){
		$output = array();
	if(is_array($vars)):
		$arrayRequired = array();
		foreach($attrib as $attribute):
				$name = $attribute['name'];
				$arrayRequired['attribute_'.$name] = $attribute['option'];
		endforeach;
	
		foreach($vars as $variation):
			$array_intersect = array_intersect_assoc($variation['attributes'],$arrayRequired);
			$attribCount = count($arrayRequired);
			if( count($array_intersect) == $attribCount):
				$array = array(
					'id'=>$variation['variation_id'],
					'price' =>$price,
					'attributes' => $attrib
					);
					
				$output = $array;
				break;
			endif;
		endforeach;
	endif;
	
	return $output;
}

function tfc_filter_current_data($curData,$newData){
	$output = array();
	
	$newData = json_encode($newData);
	
	if(is_array($curData) && is_array($newData)):
		if(isset($curData) && isset($newData) && !empty($curData) && !empty($newData)):
			foreach($curData as $data):
				$id = $data['meta']['_tfc_original_id'];
				$updatedAt = $data['meta']['_tfc_updated_at'];
				
				foreach($newData as $data2):
					if ($data2['id'] == $id && $data2['updated_at'] == $updatedAt):
						//print_r($data2);
						//array_push($output,$data2);
						break;
					endif;
	          	
				endforeach;
			endforeach;
		endif;
		
	endif;
	
	//return $output;
}