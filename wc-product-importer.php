<?php
/*
Plugin Name: WC Product Importer using REST API
Description: Bulk Import Products using REST API
Author: Eugene Empabido
Version: None
*/

//add example admin page, and register hook to enqueue needed scripts and styles

define('TFC_DIR', dirname(__FILE__));

include TFC_DIR.'/functions.php';
include TFC_DIR.'/cron.php';

add_action( 'admin_init', 'tfc_admin_init' );
add_action( 'admin_init', 'tfc_register_settings' );
add_action('admin_menu', 'tfc_admin_menu');


function tfc_admin_menu() {
    $page = add_menu_page('TFC Api', 'TFC Api', 'manage_woocommerce', 'tfc_page_slug', 'tfc_page_handler');
    add_action('admin_print_scripts-' . $page, 'tfc_admin_print_scripts');
}

function tfc_admin_init() {
   /* Register our stylesheet. */
   wp_register_style( 'tfcStylesheet', plugins_url('style.css', __FILE__) );
}
 
//register and enqueue needed scripts and styles here
function tfc_admin_print_scripts() {
    wp_enqueue_script('jquery');
	wp_enqueue_style( 'tfcStylesheet' );
}

function tfc_register_settings(){
	register_setting( 'tfc-option-group', 'tfc_options' );
}

function tfc_get_opttion($options,$type,$field){
	$output = '';
	if(isset($options[$type][$field]) && !empty($options[$type][$field])):
		$output = $options[$type][$field];
	endif;
	
	return $output;
}

//page handler is simple function that renders page
function tfc_page_handler() {
	?>
	<div class="wrap">
		<h1><?php _e("Settings","tfc"); ?></h1>
		<form method="post" action="options.php"> 
			<?php 
				settings_fields( 'tfc-option-group' );
				do_settings_sections( 'tfc-option-group' );
				
				$tfcOptions = get_option('tfc_options');
				
			?>
			<table class="form-table">
				<tbody>
					<tr><th colspan="2"><?php _e("Main Site API Keys","tfc"); ?></th></tr>
					<tr>
						<td><label for="tfc_ms_site_url"><?php _e("Site Url","tfc"); ?></label></td>
						<td><input type="tex" class="regular-text" name="tfc_options[ms][site_url]" id="tfc_ms_site_url" value="<?php print tfc_get_opttion($tfcOptions,'ms','site_url'); ?>" /></td>
					</tr>
					<tr>
						<td><label for="tfc_ms_consumer_key"><?php _e("Consumer Key","tfc"); ?></label></td>
						<td><input type="tex" class="regular-text" name="tfc_options[ms][consumer_key]" id="tfc_ms_consumer_key" value="<?php print tfc_get_opttion($tfcOptions,'ms','consumer_key'); ?>" /></td>
					</tr>
					<tr>
						<td><label for="tfc_ms_secret_key"><?php _e("Secret Key","tfc"); ?></label></td>
						<td><input type="tex" class="regular-text" name="tfc_options[ms][secret_key]" id="tfc_ms_secret_key" value="<?php print tfc_get_opttion($tfcOptions,'ms','secret_key'); ?>" /></td>
					</tr>
					<tr><th colspan="2"><?php _e("Site API Keys","tfc"); ?></th></tr>
					<tr>
						<td><label for="tfc_site_consumer_key"><?php _e("Consumer Key","tfc"); ?></label></td>
						<td><input type="tex" class="regular-text" name="tfc_options[site][consumer_key]" id="tfc_site_consumer_key" value="<?php print tfc_get_opttion($tfcOptions,'site','consumer_key'); ?>" /></td>
					</tr>
					<tr>
						<td><label for="tfc_site_secret_key"><?php _e("Secret Key","tfc"); ?></label></td>
						<td><input type="tex" class="regular-text" name="tfc_options[site][secret_key]" id="tfc_site_secret_key" value="<?php print tfc_get_opttion($tfcOptions,'site','secret_key'); ?>" /></td>
					</tr>
				</tbody>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	
	<div class="wrap tfcPlugin tfcProcess">
		<h1>TFC API</h1>
		
	<?php 
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
	
		$clientMS = new WC_API_Client( 
			tfc_get_opttion($tfcOptions,'ms','site_url'), 
			tfc_get_opttion($tfcOptions,'ms','consumer_key'), 
			tfc_get_opttion($tfcOptions,'ms','secret_key'), 
			$options 
		);
	// products
	$step = (!empty($_GET['step']) ? $_GET['step'] : 0);
	$products = $clientMS->products->get(null,array( 'filter[limit]' => -1, 'filter[meta]' => true));
	$totalProducts = count($products->products);
	$groupProducts = array_chunk($products->products, 5);
	$count = count($groupProducts);
	$adminURL = admin_url( 'admin.php?page=tfc_page_slug&step=0');
	$cronURL = admin_url( 'admin.php?page=tfc_page_slug&cron=1');
	
	$productArgs = array( 'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => -1 );

	$productQuery = new WP_Query( $productArgs );
	
	if($totalProducts != 0):
	?>
	
	    <h1>
			<?php if($productQuery->found_posts == 0): ?><a href="<?php print $adminURL; ?>" class="tfcProcess page-title-action">Insert Products</a><?php endif; ?>
			<?php if($productQuery->found_posts != 0): ?><a href="<?php print $cronURL; ?>" class="tfcUpdate page-title-action">Run Cron</a><?php endif; ?>
		</h1>
		<div id="message" class="updated notice below-h2 tfcProcessMessage">
			<?php if($productQuery->found_posts == 0): ?>
				<p class="tfcProductProgress" ><span class="tfcCompleted">0</span> of <span class="tfcTotal"><?php print $totalProducts; ?> Products</span></p>
				<p class="tfcProductSuccess" ><?php print $totalProducts; ?> Products successfully created.</p>
			<?php else: ?>
				<p class="tfcProductProgress" >Found <span class="tfcCompleted">0</span> of <span class="tfcTotal"><?php print $totalProducts; ?> Products</span></p>
				<p class="tfcProductSuccess" ><span class="tfcCompleted"><?php print $totalProducts; ?></span> product(s) successfully updated.</p>
			<?php endif; ?>
		</div>
		
		<div class="tfcProgress"></div>
	<?php else: ?>
		<div id="message" class="error notice below-h2">
			<p>No product available from the main site.</p>
		</div>
	<?php endif; ?>
	
	<script>
		
		jQuery(document).ready(function($){
			var Aajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
			var $step = 0;
			var $total = <?php echo $totalProducts; ?> ;
			var $completed = 0;
			var $products = <?php echo json_encode($groupProducts); ?>;
			
			var $result = 0;
			var $datas = {
					action: 'process_product',
					data: $products[$step],
					step: $step,
					completed: $completed,
					total: $total,
				}
				
			function process_step( step, data ) {
				
				jQuery.post(Aajaxurl,data, function(response) {
					jQuery('.tfcProgress').html('');
					
					if(response.message == 'done'){
						$step = parseInt(response.step,10) + 1;
						$completed = response.completed;
						$result = Math.round(($completed / $total) * 100);
						$datas = {
							action: 'process_product',
							data: $products[$step],
							step: $step,
							completed: $completed,
							total: $total,
						}
						
						jQuery('.tfcPlugin .tfcProcessMessage p.tfcProductProgress span.tfcCompleted').html('');
						jQuery('.tfcPlugin .tfcProcessMessage p.tfcProductProgress span.tfcCompleted').html($completed);
						console.log('<?php print tfc_get_memory_usage(); ?>');
						if($completed < $total){
							process_step( $step, $datas);
							jQuery('.tfcProgress').animate({ width: $result+'%' }, 800, function() {});
							
						}else if($completed = $total){
							jQuery('.tfcProgress').animate({ width: $result+'%' }, 800, function() {});
							jQuery('.tfcPlugin .tfcProcessMessage p.tfcProductProgress').hide();
							jQuery('.tfcPlugin .tfcProcessMessage  p.tfcProductSuccess').show();
							jQuery('.tfcProgress').fadeOut();
							jQuery('.tfcProgress').animate({ width: '3%' }, 800, function() {});
						}else{
							
						}
						
						
					}else if(response.message == 'completed'){
						jQuery('.tfcProgress').html('COMPLETED');
					}else{
						jQuery('.tfcProgress').html('FAILED');
					}
					jQuery('.tfcProgress').html(response);
				}).fail(function (response) {
					
				});

			}
			
			jQuery('a.tfcProcess').live( 'click', function(e) {
				e.preventDefault();
				jQuery('.tfcPlugin #message').show();
				jQuery('.tfcProgress').animate({ width: '1%' }, 800, function() {});
				// start the process
				process_step( 0, $datas);

			});
			
			var $newStep = 0;
			var $newCompleted = 0;
			var $newTotal = 0;
			var $newdatas = {
					action: 'tfc_update_product',
					data: $products[$newStep],
					step: $newStep,
					completed: $completed,
					total: $total,
					updated: $newTotal,
				}
				
			function update_products( step, data ) {
				
				jQuery.post(Aajaxurl,data, function(response) {
					jQuery('.tfcProgress').html('');
					
					if(response.message == 'done'){
						$newStep = parseInt(response.step,10) + 1;
						$newCompleted = response.completed;
						$newUpdated = response.updated;
						$result = Math.round(($newCompleted / $total) * 100);
						$newdatas = {
							action: 'tfc_update_product',
							data: $products[$newStep],
							step: $newStep,
							completed: $newCompleted,
							total: $total,
							updated: $newUpdated,
						}
						
						jQuery('.tfcPlugin .tfcProcessMessage p.tfcProductProgress span.tfcCompleted').html('');
						jQuery('.tfcPlugin .tfcProcessMessage p.tfcProductProgress span.tfcCompleted').html($newUpdated);
						console.log('<?php print tfc_get_memory_usage(); ?>');
						if($newCompleted < $total){
							update_products( $newStep, $newdatas);
							jQuery('.tfcProgress').animate({ width: $result+'%' }, 800, function() {});
							
						}else if($newCompleted = $total){
							jQuery('.tfcProgress').animate({ width: $result+'%' }, 800, function() {});
							jQuery('.tfcPlugin .tfcProcessMessage p.tfcProductProgress').hide();
							jQuery('.tfcPlugin .tfcProcessMessage  p.tfcProductSuccess span.tfcCompleted').html($newUpdated);
							jQuery('.tfcPlugin .tfcProcessMessage  p.tfcProductSuccess').show();
							jQuery('.tfcProgress').fadeOut();
							jQuery('.tfcProgress').animate({ width: '3%' }, 800, function() {});
						}else{
							
						}
						
						
					}else if(response.message == 'completed'){
						jQuery('.tfcProgress').html('COMPLETED');
					}else{
						jQuery('.tfcProgress').html('FAILED');
					}
					jQuery('.tfcProgress').html(response);
				}).fail(function (response) {
					
				});

			}
			jQuery('a.tfcUpdate').live( 'click', function(e) {
				e.preventDefault();
				jQuery('.tfcPlugin #message').show();
				jQuery('.tfcProgress').animate({ width: '1%' }, 800, function() {});
				// start the process
				update_products( 0, $newdatas);

			});
		});
	</script>

<?php

	} catch ( WC_API_Client_Exception $e ) {
		
		
		if ( $e instanceof WC_API_Client_HTTP_Exception ) { ?>
			<div id="message" class="error notice below-h2">
				<p>Invalid Main Site API Keys!</p>
			</div>
		<?php }
	}
	else:
		?>
			<div id="message" class="error notice below-h2">
				<p>Invalid API Keys!</p>
			</div>
		<?php 
	endif;
?>

	</div>
	
<?php 
}

function process_product(){
	
    //try to turn off error reporting
    @error_reporting(0);
	require_once( TFC_DIR.'/lib/woocommerce-api.php' );
	
	$options = array(
		'debug'           => true,
		'return_as_array' => false,
		'validate_url'    => false,
		'timeout'         => 30,
		'ssl_verify'      => false,
	);
	$products = $_POST['data'];
	$newProducts = json_decode(json_encode($products), true);
	$productCount = count($newProducts);
	$total = $_POST['total'];
	$completed = $_POST['completed'];
	$step = $_POST['step'];
	$count = 1;
	
	$tfcOptions = get_option('tfc_options');
	$siteUrl = site_url();
	
    $clientTFC = new WC_API_Client( 
		$siteUrl,
		tfc_get_opttion($tfcOptions,'site','consumer_key'), 
		tfc_get_opttion($tfcOptions,'site','secret_key'), 
		$options
	);
	
	if(!empty($newProducts) && is_array($newProducts) ):
		foreach($newProducts as $newProduct):
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
			
			//Create Products
			$clientTFC->products->create($product);
			
			if($count == $productCount):
				$newcompleted = $completed + $productCount;
				$message = ($completed == $total ? 'completed' :'done');
				
				$return = array(
						'message'	=> $message,
						'step'		=> $step,
						'completed' => $newcompleted
				);
				
				wp_send_json($return);
			endif;
			//print "<pre>";
				//print_r($newProduct['variations']);
			//print "</pre>";
			$count++;
		endforeach;
		
	endif;
	
	
	
	die(); // this is required to return a proper result
}

add_action('wp_ajax_process_product', 'process_product');

function tfc_update_product(){
	
    //try to turn off error reporting
    @error_reporting(0);
	require_once( TFC_DIR.'/lib/woocommerce-api.php' );
	
	$options = array(
		'debug'           => true,
		'return_as_array' => false,
		'validate_url'    => false,
		'timeout'         => 30,
		'ssl_verify'      => false,
	);
	
	$products = $_POST['data'];
	$newProducts = json_decode(json_encode($products), true);
	$productCount = count($newProducts);
	$total = $_POST['total'];
	$updated = $_POST['updated'];
	$completed = $_POST['completed'];
	$step = $_POST['step'];
	$count = 1;
	
	$tfcOptions = get_option('tfc_options');
	$siteUrl = site_url();
	
    $clientTFC = new WC_API_Client( 
		$siteUrl,
		tfc_get_opttion($tfcOptions,'site','consumer_key'), 
		tfc_get_opttion($tfcOptions,'site','secret_key'), 
		$options
	);
	
	if(!empty($newProducts) && is_array($newProducts) ):
			foreach($newProducts as $newProduct):
				
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
						$exclude = get_field('_tfc_exclude_from_update',$searchQuery->post->ID);
						$thisVariations = new WC_Product_Variable($searchQuery->post->ID);
						$variables = $thisVariations->get_available_variations();
						
						//Check if Product is modfied
						if($dateUpdated != $updatedAt && $exclude == 0):
							
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
							$updated++;
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
					$updated++;
				endif;

				if($count == $productCount):
					$newcompleted = $completed + $productCount;
					$message = ($completed == $total ? 'completed' :'done');
					
					$return = array(
							'message'	=> $message,
							'step'		=> $step,
							'completed' => $newcompleted,
							'updated'   => $updated
					);
					
					wp_send_json($return);
				endif;
				//print "<pre>";
					//print_r($newProduct['variations']);
				//print "</pre>";
				$count++;
			endforeach;
		
	endif;
	
	
	
	die(); // this is required to return a proper result
}

add_action('wp_ajax_tfc_update_product', 'tfc_update_product');

// Schedule Cron Job Event
function tfc_plugin_product_cron_activation() {
	if ( ! wp_next_scheduled( 'tfc_product_cron_job' ) ) {
		wp_schedule_event( time(), 'daily', 'tfc_product_cron_job');
	}
}

//register_activation_hook (__FILE__, 'tfc_plugin_product_cron_activation');

//Unschedule Event
function tfc_plugin_product_cron_deactivate(){
	// find out when the last event was scheduled
	$timestamp = wp_next_scheduled ('tfc_product_cron_job');
	// unschedule previous event if any
	wp_unschedule_event ($timestamp, 'tfc_product_cron_job');
}

//register_deactivation_hook (__FILE__, 'tfc_plugin_product_cron_deactivate'); 
