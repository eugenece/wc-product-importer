<?php

// Scheduled Action Hook
function tfc_plugin_product_update() {
	$tfcOptions = get_option('tfc_options');
	$siteUrl = site_url();
	$tfcAPICHeck = tfc_check_APIs($tfcOptions);
	
	if($tfcAPICHeck == true):
		require_once( TFC_DIR.'/lib/woocommerce-api.php' );
		
		$options = array(
			'debug'           => true,
			'return_as_array' => false,
			'validate_url'    => false,
			'timeout'         => 30,
			'ssl_verify'      => false,
		);
		
	    $_ajax_nonce = wp_create_nonce(basename(__FILE__));
	
	try {
		// products
		$step = (!empty($_GET['step']) ? $_GET['step'] : 0);
		$clientMS = new WC_API_Client( 
			tfc_get_opttion($tfcOptions,'ms','site_url'), 
			tfc_get_opttion($tfcOptions,'ms','consumer_key'), 
			tfc_get_opttion($tfcOptions,'ms','secret_key'), 
			$options 
		);
	
		$clientTFC = new WC_API_Client( 
			$siteUrl,
			tfc_get_opttion($tfcOptions,'site','consumer_key'), 
			tfc_get_opttion($tfcOptions,'site','secret_key'), 
			$options
		);
		
		$productsMS = $clientMS->products->get(null,array( 'filter[limit]' => -1,'filter[meta]' => true ));
		$products = $clientTFC->products->get(null,array( 'filter[limit]' => -1,'filter[meta]' => true ));
	
		$newProducts = json_decode(json_encode($productsMS),true);
		$productCount = count($newProducts);
		$total = $_POST['total'];
		$completed = $_POST['completed'];
		$step = $_POST['step'];
		$count = 1;
		
		if(!empty($newProducts) && is_array($newProducts) ):
			foreach($newProducts['products'] as $newProduct):
				
				$id = $newProduct['id'];
				$sku = $newProduct['sku'];
				$title = $newProduct['title'];
				$type = $newProduct['type'];
				$price = $newProduct['regular_price'];
				$description = $newProduct['description'];
				$categories = $newProduct['categories'];
				$tags = $newProduct['tags'];
				$images = $newProduct['images'];
				$featured = $newProduct['featured_src'];
				$meta = $newProduct['meta'];
				$updatedAt = $newProduct['updated_at'];
				
				$product = array( 
					'title' => $title,
					'sku' => $sku,
					'type' => $type, 
					'regular_price' => $price, 
					'description' => $description
				);
				$searchArgs = array(
					'post_type'=>'product',
					'meta_query' => array(
						array(
							'field' => '_tfc_original_id',
							'value' => $id,
							'compare' => '='
						),
					)
				);
				
				$searchQuery = new WP_Query($searchArgs);
				
				if($searchQuery->have_posts()):
					while($searchQuery->have_posts()): $searchQuery->the_post();
						$dateUpdated = get_field('_tfc_updated_at',$searchQuery->post->ID);
						$originalID = get_field('_tfc_original_id',$searchQuery->post->ID);
						$thisVariations = new WC_Product_Variable($searchQuery->post->ID);
						$variables = $thisVariations->get_available_variations();
						
						//Check if Product is modfied
						if($dateUpdated != $updatedAt):
							
							//Set a base value
							$modProduct = array(
								'title' => $title,
								'sku' => $sku,
								'type' => $type, 
								'regular_price' => $price, 
								'description' => $description
							);
							
							//Set Custom Fields
							if(!empty($newProduct['meta'])):
								$modProduct['custom_meta'] = array();
							endif;
							
							//Set Categories
							if(!empty($categories) && is_array($categories)):
								$modProduct['categories'] = tfc_get_terms_ids($categories,'product_cat');
							endif;
							
							//Set Tags
							if(!empty($tags) && is_array($tags)):
								$modProduct['tags'] = tfc_get_terms_ids($tags,'product_tag');
							endif;
							
							//Set Attributes and Variations
							if($type == 'variable'):
								$modProduct['variations'] = array();
								//$modProduct['attributes'] = array();
							endif;
							
							//Initial Update
							$clientTFC->products->update($searchQuery->post->ID,$modProduct);
							
							//Set Attributes and Variations
							if($type == 'variable'):
								$variableProduct = array();
								//Set Custom Fields
								if(!empty($newProduct['meta'])):
									$variableProduct['custom_meta'] = $newProduct['meta'];
								endif;
								
								//Set Images
								if(!empty($images) && is_array($images)):
									$variableProduct['custom_meta']['_tfc_images']['images'] = array();
									foreach($images as $image):
										
										$theImages = array(
											'src' => tfc_change_url($image['src']),
											'position' => $image['position']
										);
										
										array_push($variableProduct['custom_meta']['_tfc_images']['images'],$theImages);
									endforeach;
									
								endif;
								
								//Set Featured Image
								if(!empty($featured) && is_array($featured)):
									$variableProduct['custom_meta']['_tfc_images']['featured'] = tfc_change_url($featured);				
								endif;
								
								//Set Attributes
								$variableProduct['attributes'] = array();
								if(!empty($newProduct['attributes'])):
									foreach($newProduct['attributes'] as $attribute):
						
										$theAttributes = array(
											'name'=>$attribute['name'],
											'slug'=>$attribute['slug'],
											'position'=>'0',
											'visible'=>true,
											'options' => $attribute['options'],
											'variation'=>true
										);
										array_push($variableProduct['attributes'],$theAttributes);
									endforeach;
								endif;
								
								//Set Variations
								$variationIDS = array();
								$variableProduct['variations'] = array();
								if(!empty($newProduct['variations'])):
									foreach($newProduct['variations'] as $variation):
									
										$variableProduct['custom_meta']['_tfc_images']['variation_images'] = array();
										if(!empty($variation['image'])):
											foreach($variation['image'] as $varImage):
												$theVarImages = array(
													'src' => tfc_change_url($varImage['src']),
													'position' => $varImage['position']
												);
												
												array_push($variableProduct['custom_meta']['_tfc_images']['variation_images'],$theVarImages);
											endforeach;
										endif;
										
										$theVariationAttribs = array();
										if(!empty($variation['attributes'])):
											foreach($variation['attributes'] as $varAtt):
												$theVarAtt = array(
													'name' => $varAtt['name'],
													'option' => $varAtt['option']
												);
												
												array_push($theVariationAttribs,$theVarAtt);
											endforeach;
										endif;
										
										
										$checkVars = tfc_check_variation($theVariationAttribs,$variables,$variation['regular_price']);
										if(!empty($checkVars)):
											array_push($variationIDS,$checkVars);
										else:
											$variableProduct['type'] = 'variable';
											$theVariation = array(
												'regular_price'=>$variation['regular_price'],
												'virtual'=>false,
												'attributes' => $theVariationAttribs
											);
											
											array_push($variableProduct['variations'],$theVariation);
										endif;
									endforeach;
								endif;
								
								//Set Value for the Original ID and Date Updated
								$variableProduct['custom_meta']['_tfc_original_id'] = $originalID;
								$variableProduct['custom_meta']['_tfc_updated_at'] = $updatedAt;
								
								//Final Update
								$clientTFC->products->update($searchQuery->post->ID,$variableProduct);
								
								//Updating Variations
								tfc_update_variations($clientTFC,$variationIDS);
								
							endif;
						endif;
					endwhile; wp_reset_postdata();
				else:
					//Set Custom Fields
					if(!empty($newProduct['meta'])):
						$product['custom_meta'] = $newProduct['meta'];
					endif;
					
					//Set Categories
					if(!empty($categories) && is_array($categories)):
						$product['categories'] = tfc_get_terms_ids($categories,'product_cat');
					endif;
					
					//Set Tags
					if(!empty($tags) && is_array($tags)):
						$product['tags'] = tfc_get_terms_ids($tags,'product_tag');
					endif;
					
					//Set Images
					if(!empty($images) && is_array($images)):
						$product['custom_meta']['_tfc_images']['images'] = array();
						foreach($images as $image):
							
							$theImages = array(
								'src' => tfc_change_url($image['src']),
								'position' => $image['position']
							);
							
							array_push($product['custom_meta']['_tfc_images']['images'],$theImages);
						endforeach;
						
					endif;
					
					//Set Featured Image
					if(!empty($featured) && is_array($featured)):
						$product['custom_meta']['_tfc_images']['featured'] = tfc_change_url($featured);				
					endif;
					
					//Set Attributes and Variations
					if($type == 'variable'):
						$product['attributes'] = array();
						if(!empty($newProduct['attributes'])):
							foreach($newProduct['attributes'] as $attribute):
				
								$theAttributes = array(
									'name'=>$attribute['name'],
									'slug'=>$attribute['slug'],
									'position'=>'0',
									'visible'=>true,
									'options' => $attribute['options'],
									'variation'=>true
								);
								array_push($product['attributes'],$theAttributes);
							endforeach;
						endif;
						
						$product['variations'] = array();
						if(!empty($newProduct['variations'])):
							foreach($newProduct['variations'] as $variation):
							
								$product['custom_meta']['_tfc_images']['variation_images'] = array();
								if(!empty($variation['image'])):
									foreach($variation['image'] as $varImage):
										$theVarImages = array(
											'src' => tfc_change_url($varImage['src']),
											'position' => $varImage['position']
										);
										
										array_push($product['custom_meta']['_tfc_images']['variation_images'],$theVarImages);
									endforeach;
								endif;
								
								$theVariationAttribs = array();
								if(!empty($variation['attributes'])):
									foreach($variation['attributes'] as $varAtt):
										$theVarAtt = array(
											'name' => $varAtt['name'],
											'option' => $varAtt['option']
										);
										
										array_push($theVariationAttribs,$theVarAtt);
									endforeach;
								endif;
								
								$theVariation = array(
									'regular_price'=>$variation['regular_price'],
									'virtual'=>false,
									'attributes' => $theVariationAttribs
								);
								
								array_push($product['variations'],$theVariation);
							endforeach;
						endif;
						
					endif;
					
					$product['custom_meta']['_tfc_original_id'] = $id;
					$product['custom_meta']['_tfc_updated_at'] = $updatedAt;
					$clientTFC->products->create($product);
				endif;

				$count++;
			endforeach;
		
	endif;
	} catch ( WC_API_Client_Exception $e ) {
			if ( $e instanceof WC_API_Client_HTTP_Exception ) {  }
		}
	else:
		
	endif;
?>

	</div>
<?php 
}

add_action( 'tfc_product_cron_job', 'tfc_plugin_product_update' );
