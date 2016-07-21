<?php

class OPTION_PARAM_{
	var $parent_id;
	var $level;
	function OPTION_PARAM_($parent_id, $level){
		$this->parent_id = $parent_id;
		$this->level = $level;
		return $this;
	}
}

class OPTION_NODE_ extends DB_TREE_NODE
{
	function drawNode($param)
	{
		echo '<option value="'.$this->id.'" '.($this->id == $param->parent_id?"selected":"").' '.($this->is_visible=="No"?'style="color:gray;"':'').'>';
		for ($i=0; $i<$param->level*4; $i++)
		{
			echo "&nbsp;";
		}
		echo gs($this->caption).'</option>';
		if (count($this->nodes)>0)
		{
			reset($this->nodes);
			foreach ($this->nodes as $id => $node)
			{
				$node->drawNode(
					new OPTION_PARAM_($param->parent_id, $param->level+1)
				);
			}
		}
	}
}

class plugin_manual_order extends cartPlugin 
{
	var $plugin_id          = "manual_order";
	var $plugin_group       = "misc";
	var $plugin_caption     = "Manual Order";
	var $plugin_description = "Set up a manual order from the admin panel";
	var $db2;
	
	function plugin_manual_order()
	{	
		$this->interface = array();
		$this->interface["title"] = $this->plugin_caption;
		$this->interface["url"]   = 'admin.php?p=plugin&plugin_id=' . $this->plugin_id;
		$this->interface["class"] = "ic-plugin";
		$this->interface["group"] = "misc";
		return $this;	
	}
	
	function setupPlugin( &$db )
	{	
		$this->db = $db;
	}

	function runPlugin( &$db, &$settings, &$request )
	{
	
		require_once 'content/engine/engine_config.php';
		require_once 'content/engine/engine_mysql.php';
		require_once 'content/engine/engine_functions.php';
		require_once 'content/engine/engine_order.php';
		require_once 'content/engine/engine_user.php';
		require_once 'content/classes/class.catalog.php';
		require_once 'content/classes/class.products.php';
		require_once 'content/classes/class.product_attribute.php';
		require_once 'content/engine/engine_url_'.($settings["USE_MOD_REWRITE"]=="YES" ? "rewrite" : "default").'.php';

		$this->db2 = new DB;
		$this->db2->init(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

		parent::runPlugin( $db, $settings, $request );
		extract( $request );

		pageHeader( "Manual Order" );

		$user = new USER( $db, $settings );

		$userstr  = "new customer";
		$resetstr = "";

		if( isset( $uid ) )
		{
			$uid = intval( $uid );
			$user->id = $uid;

			if( $user->getUserData() )
				$_SESSION['manual_order'] = $uid;

			else
				$user->id = 0;

			header( "Location: " . $this->interface["url"] );
		}

		if( isset( $resetuser ) )
		{
			unset( $_SESSION['manual_order'] );
			$user->id = 0;
			$userstr = "new customer";
			header( "Location: " . $this->interface["url"] );
		}

		if( isset( $_SESSION['manual_order'] ) )
		{
			$user->id = $_SESSION['manual_order'];
			$user->getUserData();
			$userstr  = "<em>" . htmlentities( $user->data['fname'] ) . " " . htmlentities( $user->data['lname'] ) . "</em>";
			$resetstr = " <a href='" . $this->interface["url"] . "&resetuser'>Reset user</a>";
		}

		$order = new ORDER( $db, $settings, $user, true );

		if( isset( $empty ) )
		{
			$order->clearItems();
			header( "Location: " . $this->interface["url"] );
		}

		$order->getOrderData();
		$order->recalcOrderData();

		if( isset( $removepid ) )
		{
			$order->removeItem( $removepid );
			$order->recalcOrderData();
			header( "Location: " . $this->interface["url"] );
		}

		if( isset( $addtocart ) )
		{
			$attrib = array();
			$pids   = explode( ",", $addtocart );

			foreach( $pids as $pid )
			{
				if( isset( $_REQUEST[$pid . '_attributes'] ) && is_array( $_REQUEST[$pid . '_attributes'] ) )
					$attrib = $_REQUEST[$pid . '_attributes'];

				$order->addItem( htmlentities( $pid ), 1, $attrib );
			}

			header( "Location: " . $this->interface["url"] );
		}

		$items = "<br /><br />";
		$options = "";

		if( $order->getItemsCount() > 0 )
		{
			$order_items = $order->getOrderItems();

			if( is_array( $order_items ) && !empty( $order_items ) )
			{
				foreach( $order_items as $order_item )
				{
					if( $order_item['attributes_count'] > 0 )
						$options = nl2br( $order_item['options'] ) . "<br /><br />";

					$items .= "<em>" . htmlentities( $order_item['title'] ) . "</em> x " . $order_item['quantity'] . " : " . getAdminPrice( $order_item['price'] * $order_item['quantity'] ) . " <a href='" . $this->interface["url"] . "&removepid=" . $order_item['ocid'] . "'><img src='images/icons/a/delete.png' title='Remove " . htmlentities( $order_item['title'] ) . "' /></a><br />" . $options;

				}
			}

			$items .= "<br /><a href='" . $this->interface["url"] . "&checkout'>Checkout as " . $userstr . "</a> | <a href='" . $this->interface["url"] . "&empty'>Empty cart</a><br /><br />";
		}


		pageNote( array( array( "class" => "ic-info", "text" => "<strong>" . ucfirst( $userstr ) . "'s cart: " . $order->getItemsCount() . " item(s) / " . getAdminPrice( $order->getSubtotalAmount() ) . "</strong>" . $items . $resetstr, "url" => "", "visible" => true ) ) );

		if( !isset( $checkout ) )
		{

			$action = isset($action) ? trim($action) : "";
			$lname = isset($lname) ? trim($lname) : "";
			$email = isset($email) ? trim($email) : "";
			$login = isset($login) ? trim($login) : "";
			$phone = isset($phone) ? trim($phone) : "";
			$company = isset($company) ? trim($company) : "";
			$ec_users = isset($ec_users) ? true : false;
			
			$from_y = isset($from_y) ? trim($from_y) : "";
			$from_m = isset($from_m) ? trim($from_m) : "";
			$from_d = isset($from_d) ? trim($from_d) : "";
			$to_y = isset($to_y) ? trim($to_y) : "";
			$to_m = isset($to_m) ? trim($to_m) : "";
			$to_d = isset($to_d) ? trim($to_d) : "";
			
			
			$from_date = false;
			$to_date = false;
			
			// Setting date
			if (isset($from_y) && isset($from_m) && isset($from_d) && $from_y != '' && $from_m != '' && $from_d != '')
			{
				$from_date =  intval($from_y)."-".intval($from_m)."-".intval($from_d).' 00:00:01';
			}
			
			if (isset($to_y) && isset($to_m) && isset($to_d) && $to_y != '' && $to_m != '' && $to_d != '')
			{
				$to_date =  intval($to_y)."-".intval($to_m)."-".intval($to_d).' 00:00:01';
			}
		?>
		<form action="<?=$this->interface["url"]?>" method="GET">
			<input type="hidden" name="p" value="plugin" />
			<input type="hidden" name="plugin_id" value="<?=$this->plugin_id?>" />	
			<input type="hidden" name="action" value="search_user" />

			<div class="admin-search-form">
				<div class="admin-search-form-header">Search for a customer to checkout as (skip this if new customer)</div>
				<div class="admin-search-form-wrap clearfix">
					<div class="admin-search-form-item">
						<label for="lname">Last Name</label>
						<input type="text" id="lname" name="lname" value="<?=htmlspecialchars($lname)?>"/>
					</div>
					<div class="admin-search-form-item">
						<label for="email">Email Address</label>
						<input type="text" id="email" name="email" value="<?=htmlspecialchars($email)?>"/>
					</div>
					<div class="admin-search-form-item">
						<label for="login">Username</label>
						<input type="text" id="login" name="login"  value="<?=htmlspecialchars($login)?>"/>
					</div>
					<div class="admin-search-form-item">
						<label for="company">Company</label>
						<input type="text" id="company" name="company" value="<?=htmlspecialchars($company)?>"/>
					</div>
					<div class="admin-search-form-item">
						<label for="phone">Phone Number</label>
						<input type="text" id="phone" name="phone"  value="<?=htmlspecialchars($phone)?>"/>
					</div>
					<div class="admin-search-form-item">
						&nbsp;<br/>
						<input style="display:inline-block;" type="checkbox" id="search-express-checkout-users" value="1" name="ec_users" <?php echo $ec_users ? "checked":"";?>/>
						<label style="display:inline-block;" for="search-express-checkout-users">Search Express Checkout Users</label>
					</div>
					<div class="admin-search-form-item">
						<label>From</label>
						<select name="from_m" class="date-month">
							<option value="">--</option>
							<?php for($i=1; $i<=12; $i++){?><option value="<?=$i?>" <?=$i == $from_m?"selected":""?>><?=$months[$i]?></option><?}?>
						</select>
						<select name="from_d" class="date-day">
							<option value="">--</option>
							<?php for($i=1; $i<=31; $i++){?><option value="<?=$i?>" <?=$i == $from_d?"selected":""?>><?=$i?></option><?}?>
						</select>
						<select name="from_y" class="date-year">
							<option value="">--</option>
							<?php for($i=2004; $i<=date("Y")+10; $i++){?><option value="<?=$i?>" <?=$i == $from_y?"selected":""?>><?=$i?></option><?}?>
						</select>
					</div>
					<div class="admin-search-form-item">
						<label>To</label>
						<select name="to_m" class="date-month">
							<option value="">--</option>
							<? for($i=1; $i<=12; $i++){?><option value="<?=$i?>" <?=$i == $to_m?"selected":""?>><?=$months[$i]?></option><?}?>
						</select>
						<select name="to_d" class="date-day">
							<option value="">--</option>
							<? for($i=1; $i<=31; $i++){?><option value="<?=$i?>" <?=$i == $to_d?"selected":""?>><?=$i?></option><?}?>
						</select>
						<select name="to_y" class="date-year">
							<option value="">--</option>
							<? for($i=2004; $i<=date("Y")+10; $i++){?><option value="<?=$i?>" <?=$i == $to_y?"selected":""?>><?=$i?></option><?}?>
						</select>
					</div>
				</div>
				<div class="admin-form-buttons admin-search-form-buttons">
					<input type="submit" value="Search"/>
					<input type="reset" value="Reset Search" />
				</div>
			</div>
		</form>


		<?php
			if ($action == "search_user")
			{
				$where = "WHERE ".
					($ec_users ? "" : "login <> 'ExpressCheckoutUser' AND ").
					"lname LIKE '%".$db->escape($lname)."%'".
					"AND email LIKE '%".$db->escape($email)."%'".
					"AND login LIKE '%".$db->escape($login)."%'".
					"AND phone LIKE '%".$db->escape($phone)."%'".
					"AND company LIKE '%".$db->escape($company)."%'";
				
				if ($from_date)
				{
					$where .= " AND created_date >='".$from_date."' "; 
				}
				
				if ($to_date)
				{
					$where .= " AND created_date <='".$to_date."' ";
				}
				
				$db->query("SELECT * FROM ".DB_PREFIX."users ".$where." ORDER BY lname, fname ");
		?>
		<div class="listHeader">
			<div class="listHeaderIcon"><img src="images/icons/a/zoom.png"/></div>
			<div class="listHeaderCaption">
				Users Search Results
			</div>
			<div class="listHeaderCaption" style="float:right;font-weight:normal;">
				Users found : <?=$db->numRows()?>
			</div>
		</div>
		<?php	
				if ($db->numRows() > 0)
				{
		?>
		&nbsp;
		<table width="100%" cellpadding="0" cellspacing="0" border="0" class="admin-list-table">
			<thead>
				<th class="icon"></th>
				<th style="width:150px;">Username</th>
				<th>Name</th>
				<th style="width:150px;">Company</th>
				<th style="width:150px;">Email Address</th>
				<th></th>
			</thead>
			<tbody>
		<?php
					while ($db->moveNext())
					{
		?>
				<tr>
					<td class="icon"><img src="images/icons/a/user.png"/></td>
					<td><?=htmlspecialchars($db->col["login"])?></td>
					<td><?=htmlspecialchars($db->col["fname"]." ".$db->col["lname"])?></td>
					<td><?=htmlspecialchars($db->col["company"])?></td>
					<td><?=htmlspecialchars($db->col["email"])?></td>
					<td style="text-align:right;">
						<a href="<?=$this->interface["url"]?>&uid=<?=$db->col["uid"]?>"><img src="images/icons/a/invoice.png" title="Checkout as <?=htmlspecialchars($db->col["fname"]." ".$db->col["lname"])?>" alt="<?=htmlspecialchars($db->col["fname"]." ".$db->col["lname"])?>" /></a>
					</td>
				</tr>
		<?php
					}
		?>
			</tbody>
		</table><br /><br />
		<?php
				}
				else
				{
		?>
		<div class="listEmpty">
			Your search did not produce any results. Please try again with a different search criteria.<br/>
			<a href="<?=$this->interface["url"]?>">Go back</a>
		</div>	
		<?php
				}
			}
		
			$dbtree = new DB_TREE();
			$dbtree->collection[TREE_ROOT] = (new OPTION_NODE_(TREE_ROOT, -1, 0, "Yes", "Any"));

			$this->db->query("SELECT * FROM ".DB_PREFIX."catalog ORDER BY level, priority, name");
			$cats_count = $this->db->numRows();
			if ($cats_count > 0)
			{
				while ($this->db->moveNext())
				{
					$dbtree->addNode(
						new OPTION_NODE_(
							$this->db->col["cid"],
							$this->db->col["parent"],
							$this->db->col["level"],
							$this->db->col["is_visible"],
							$this->db->col["name"]
						)
					);
				}
			}

			$action = isset($action)?trim($action):"";
			$product_id = isset($product_id)?trim($product_id):"";
			$title = isset($title)?trim($title):"";
			$status = isset($status)?trim($status):"";
			$status = in_array($status, array("Yes", "No", "OutOfStock", "StockWarning"))?$status:"";
			$cid = isset($cid)?$cid*1:TREE_ROOT;
			$where = "";
			$sort_products_by = isset($sort_products_by)?$sort_products_by:"title";
			$sort_products_by = in_array($sort_products_by, array("title", "price_minmax", "price_maxmin", "product_id", "priority", "stock"))?$sort_products_by:"title";

			if (isset($_GET['action']) && $_GET['action'] == "search")
			{
				//$_SESSION["admin_search_product_url"] = $_SERVER["QUERY_STRING"];
				switch($sort_products_by){
					case "price_minmax" : 
					{
						$order_sql = "ORDER BY ".DB_PREFIX."products.call_for_price, ".DB_PREFIX."products.price, ".DB_PREFIX."products.title ";
						break;
					}
					case "price_maxmin" : 
					{
						$order_sql = "ORDER BY ".DB_PREFIX."products.call_for_price, ".DB_PREFIX."products.price DESC, ".DB_PREFIX."products.title ";
						break;
					}
					case "product_id" : 
					{
						$order_sql = "ORDER BY ".DB_PREFIX."products.product_id ";
						break;
					}
					case "priority" : 
					{
						$order_sql = "ORDER BY ".DB_PREFIX."products.priority DESC, ".DB_PREFIX."products.title ";
						break;
					}
					case "stock" : 
					{
						$order_sql = "ORDER BY ".DB_PREFIX."products.inventory_control, ".DB_PREFIX."products.stock, ".DB_PREFIX."products.title ";
						break;
					}
					default :
					case "title" : 
					{
						$order_sql = "ORDER BY ".DB_PREFIX."products.title ";
					}
				}

				if ($product_id != "")
				{
					$where .= ($where==""?"WHERE ":" AND ")." product_id LIKE '%".$this->db->escape($product_id)."%' ";
				}
				if ($title != "")
				{
					$where .= ($where==""?"WHERE ":" AND ")." title LIKE '%".$this->db->escape($title)."%' ";
				}
				if ($status != "")
				{
					switch ($status)
					{
						case "Yes" :
						case "No" : 
						{
							$where .= ($where==""?"WHERE ":" AND ")." ".DB_PREFIX."products.is_visible = '".$status."' ";
							break;
						}
						case "OutOfStock" : 
						{
							$where .= ($where==""?"WHERE ":" AND ")." ".DB_PREFIX."products.inventory_control != 'No' AND ".DB_PREFIX."products.stock = 0 ";
							break;
						}
						case "StockWarning" : 
						{
							$where .= ($where==""?"WHERE ":" AND ")." ".DB_PREFIX."products.inventory_control != 'No' AND ".DB_PREFIX."products.stock <= stock_warning ";
							break;
						}
					}
				}
				
				if ($cid != "")
				{
					$where .= ($where==""?"WHERE ":" AND ")." ".DB_PREFIX."products.cid = '".$cid."'"; 
				}

				//store last search product statement
				//$_SESSION["products-search-where"] = $where;
				//preselect
				$this->db->query("SELECT COUNT(*) AS c FROM ".DB_PREFIX."products ".$where);//." GROUP BY ".DB_PREFIX."products.pid");
				$this->db->moveNext();
				//calc page breaks
				$ccc = 20;
				$items_count = $this->db->col["c"];
				$pages_count = floor($items_count / $ccc) +  ((($items_count % $ccc) > 0 )?1:0);
				$pg = isset($pg)?($pg*1):1;
				$pg = (($pg<1)||($pg>$pages_count))?1:$pg;
				$pages_line = format_pages_line(
					$pg, // current pare
					$pages_count, // total pages
					7,
					$this->interface["url"].
						"&action=search".
						"&cid=".urlencode($cid).
						"&product_id=".urlencode($product_id).
						"&title=".urlencode($title).
						"&status=".$status.
						"&sort_products_by=".$sort_products_by
				);

				//select
				$this->db->query("
					SELECT pid, is_doba, product_id, title, is_visible, price, call_for_price, inventory_control, stock, attributes_count, weight FROM ".DB_PREFIX."products
					".$where."
					".$order_sql."
					LIMIT ".(($pg-1)* $ccc).", ".$ccc
				);
			}

		?><br /><br />
		<form action="<?=$this->interface["url"]?>" method="GET">
			<input type="hidden" name="p" value="plugin">
			<input type="hidden" name="plugin_id" value="<?=$this->plugin_id?>">
			<input type="hidden" name="action" value="search">

		<div class="admin-search-form">
			<div class="admin-search-form-header">Search for products to add to cart</div>
			<div class="admin-search-form-wrap clearfix">
				<div class="admin-search-form-item">
					<label>Product ID</label>
					<input type="text" name="product_id" value="<?=htmlspecialchars($product_id)?>"/>
				</div>
				<div class="admin-search-form-item">
					<label>Product Name</label>
					<input type="text" name="title" value="<?=htmlspecialchars($title)?>"/>
				</div>
				<div class="admin-search-form-item">
					<label>Category</label>
					<select name="cid">
		<?php
			$dbtree->collection[TREE_ROOT]->drawNode(
				new OPTION_PARAM_($cid, 0)
			);
		?>
					</select>
				</div>
				<div class="admin-search-form-item">
					<label>Status</label>
					<select name="status">
						<option value="any">Any</option>
						<option value="Yes" <?=$status=="Yes"?"selected":""?>>Available</option>
						<option value="No" <?=$status=="No"?"selected":""?>>Unavailable</option>
						<option value="OutOfStock" <?=$status=="OutOfStock"?"selected":""?>>Out of stock</option>
						<option value="StockWarning" <?=$status=="StockWarning"?"selected":""?>>Stock warning</option>
					</select>
				</div>
				<div class="admin-search-form-item">
					<label>Sort by</label>
					<select name="sort_products_by">
						<option value="title">Product name</option>
						<option value="price_minmax" <?=$sort_products_by=="price_minmax"?"selected":""?>>Unit price (min-max)</option>
						<option value="price_maxmin" <?=$sort_products_by=="price_maxmin"?"selected":""?>>Unit price (max-min)</option>
						<option value="product_id" <?=$sort_products_by=="id"?"selected":""?>>Product ID</option>
						<option value="priority" <?=$sort_products_by=="priority"?"selected":""?>>Priority</option>
						<option value="stock" <?=$sort_products_by=="stock"?"selected":""?>>Stock</option>
					</select>
				</div>
			</div>
			<div class="admin-form-buttons admin-search-form-buttons">
				<input type="submit" value="Search" />
				<input type="reset" value="Reset Search" />
			</div>
		</div>
		</form>

		<?php
			if ($action == "search")
			{
		?>
		<script type="text/javascript">
		$(document).ready(function(){
			$("#add-to-cart").click(function()
			{
				var selected = $("input[name='prodAdd']:checked");
				if (selected.size() > 0)
				{
					var array = new Array();
					var copy = new Array();
					var pids = "";
					var params = "";
					for (var i = 0; i < selected.size(); i++)
					{
						var form = selected[i].form.id;
						
						if (i > 0)
							pids += ",";
						pids += $(selected.get(i)).val();
						array[i] = $("#"+form).serializeArray();
					}

					copy = array;

					for (var a = 0; a < array.length; a++ )
					{
						$.each(copy, function(i)
						{
							for (var j = 0; j < array[a].length; j++ )
							{
								if(typeof(copy[a][i]) != "undefined")
								{
									
									if(copy[a][j].name == 'addtocart') copy[a].splice(j,1);
									if(copy[a][i].name == 'p') copy[a].splice(i,1);
									if(copy[a][i].name == 'plugin_id') copy[a].splice(i,1);
									if(copy[a][i].name == 'prodAdd') copy[a].splice(i,1);
									
								}
							}
						});
						params += "&" + $.param(copy[a]);
					}
					
					document.location = "<?=$this->interface["url"]?>&addtocart="+pids + params;
				}
				else
				{
					alert('No products selected');
				}
			});
			$("#selectAll").change(function()
			{
				if ($("#selectAll").attr('checked'))
				{
					$("input[name='prodAdd']").attr('checked', 'true');
				}
				else
				{
					$("input[name='prodAdd']").removeAttr('checked');
				}
			});
		});
		</script>
		<div class="listHeader">
			<div class="listHeaderIcon"><img src="images/icons/a/zoom.png"/></div>
			<div class="listHeaderCaption">
				Product Search Results
			</div>
		<?php
				if ($this->db->numRows() > 0)
				{
		?>
			<div class="listHeaderCaption" style="float:right;font-weight:normal;">
				Products found : <?=$items_count?> |
				Page(s) : <?=trim($pages_line) == ""?0:$pages_line?>
			</div>
		<?php	
				}
		?>
		</div>
		<?php
				if ($this->db->numRows() > 0)
				{
		?>
		<table cellpadding="0" cellspacing="0" border="0" width="100%" class="admin-list-table">
			<thead>
				<tr>
					<th class="icon"></th>
					<th class="icon"></th>
					<th style="width:200px;">Product ID</th>
					<th>Product Name</th>
					<th style="width:70px;">Available</th>
					<th style="width:100px;" class="text-align-right">Price</th>
					<th style="width:70px;" class="text-align-right">Stock</th>
					<th style="width:100px;">&nbsp;</th>
				</tr>
			</thead>
			<tbody>
		<?php
					while ($this->db->moveNext())
					{
						$attributes = array();

						if ($this->db->col["attributes_count"] > 0)
						{
							$this->db2->query("
								SELECT * FROM ".DB_PREFIX."products_attributes
								WHERE ".DB_PREFIX."products_attributes.pid = '".intval($this->db->col["pid"])."' AND is_active='Yes'
								ORDER BY priority, name, is_modifier
							");
							while ($this->db2->moveNext())
							{
								$attribute = $this->db2->col;
								$attribute["options"] = array();
								parseOptions($this->db2->col["options"], $this->db->col["price"], $this->db->col["weight"], $attribute["options"]);
								$attributes[$this->db2->col["paid"]] = $attribute;
							}
						}
		?>
				<tr><form id="<?=$this->db->col["product_id"]?>_form" class="add-form" action="<?=$this->interface["url"]?>" method="GET">
					<input type="hidden" name="p" value="plugin" />
					<input type="hidden" name="plugin_id" value="<?=$this->plugin_id?>" />
					<td class="icon"><input type="checkbox" name="prodAdd" value="<?= $this->db->col["product_id"]?>"/></td>
					<td class="icon"><img src="images/icons/a/<?=$this->db->col["is_doba"] == "Yes"?"record":"product"?>.png"/></td>
					<td><?=htmlspecialchars($this->db->col["product_id"])?></td>
					<td><?=htmlspecialchars($this->db->col["title"])?></td>
					<td><?=$this->db->col["is_visible"]?></td>
					<td class="text-align-right"><?=$this->db->col["call_for_price"]=="Yes"?"Call for price":getAdminPrice($this->db->col["price"])?></td>
					<td class="text-align-right"><?=$this->db->col["inventory_control"]=="Yes"?$this->db->col["stock"]:"-"?></td>
					<td class="text-align-left" id="<?=$this->db->col["product_id"]?>_attr">
		<?php
						if ($this->db->col["attributes_count"] > 0)
						{
							foreach( $attributes as $attribute )
							{
								echo '<label>' . htmlspecialchars( $attribute["caption"] ) .'</label>';

								if( $attribute["attribute_type"] == "select" )
								{
									echo '<select id="attribute_input_'.$attribute["paid"].'" name="'.$this->db->col["product_id"].'_attributes['.$attribute["paid"].']">';

									foreach( $attribute["options"] as $option )
									{
										echo '<option value="' . urlencode( $option["name"] ) . '">' . $option["name"] . ( $attribute["is_modifier"] == "Yes" ? $option["modifier"] : '' ) . '</option>';
									}

									echo '</select>';
								}
								elseif( $attribute["attribute_type"] == "radio" )
								{
									foreach( $attribute["options"] as $option )
									{
										if( !$option["first"] ) echo '<br />';

										echo '<input type="radio" id="attribute_input_'.$attribute["paid"].'" name="'.$this->db->col["product_id"].'_attributes['.$attribute["paid"].']" value="' . urlencode( $option["name"] ) . '" ' . ( $option["first"] ? 'checked="checked"' : '' ) .'/>' . $option["name"] . ( $attribute["is_modifier"] == "Yes" ? $option["modifier"] : '' );
									}
								}
								else
								{
									if( $attribute["attribute_type"] == "textarea" )
										echo '<textarea wrap="off" name="'.$this->db->col["product_id"].'_attributes['.$attribute["paid"].']" rows="' . $attribute["text_length"] . '" cols="40"></textarea>';

									else
										echo '<input type="text" name="'.$this->db->col["product_id"].'_attributes['.$attribute["paid"].']" value="" maxlength="' . $attribute["text_length"] . '"/>';
								}
							}
						}
		?>
					</td>			
					<td class="text-align-right">
						<input type="hidden" name="addtocart" value="<?=$this->db->col["product_id"]?>" />
						<input type="submit" value="Add" />
					</td></form>
				</tr>
		<?php
					}
		?>
			</tbody>
			<tfoot>
				<tr>
					<td><input type="checkbox" id="selectAll"/></td>
					<td colspan="7"><label for="selectAll">Select All</label></td>
				</tr>
				<tr>
					<td colspan="8">
						<input type="button" value="Add to Cart" id="add-to-cart" class="warning"/> &nbsp;
					</td>
				</tr>
			</tfoot>
		</table>
		&nbsp;
		<div class="listFooter">
			<div class="listFooterCaption" style="float:right;font-weight:normal;">
				Products found : <?=$items_count?> |
				Page(s) : <?=trim($pages_line) == ""?0:$pages_line?>
			</div>
		</div>
		<?php
				}
				else
				{
		?>
		<div class="listEmpty">
			Your search did not produce any results. Please try again with a different search criteria.<br/>
			<a href="<?=$this->interface["url"]?>&reset_search=true">Reset Search</a>
		</div>
		<?php
				}
			}
		}
		else
		{
			$order->setOrderNum();
			$order->setAbandonStatus();
			
			// Reset gift certificate
			if ($settings['enable_gift_cert'] == "Yes")
			{
				$giftCertificates = new GiftCertificates($db);
				$giftCertificates->UpdateOrderGCAmount($order->oid, 0.00);
				$giftCertificates->SetOrderGiftCert($order->oid, '', '', '');
				$order->gift_cert_amount = 0;
			}

			// Country / state data
			require_once("content/classes/class.regions.php");
			$regions = new Regions($db);
			view()->assign("countries_states", json_encode($regions->getCountriesStatesForSite()));
			
			// Billing data
			$billingData = $user->getBillingInfo();
			
			if (($user->express || $user->data["facebook_user"] || $user->id == 0) && $billingData["country"] == 0)
			{
				$billingIsEmpty = true;
				$billingData["country"] = "-1";
				$billingData["state"] = "-1";
			}
			else
			{
				$billingIsEmpty = false;
				$billingData = $user->getBillingInfo();
			}
			
			// Payment profiles
			$paymentProfiles = array();
			$paymentProfileId = 'billing'; //by default we use billing address
			$paymentProfileMethodId = 0;
			
			view()->assign("new_account", isset($_SESSION["facebook_new_account"]) && $_SESSION["facebook_new_account"]);
			
			require_once("content/classes/class.payment_profile.php");
			$paymentProfileManager = new Payment_Profile();

			//check are there payment profiles
			$paymentProfiles = $paymentProfileManager->getPaymentProfiles($db, $user);
			
			if ($paymentProfiles && count($paymentProfiles) > 0)
			{
				// check is payment profile already selected
				if (isset($_SESSION['opc-payment-profile-id']))
				{
					$paymentProfileId = $_SESSION['opc-payment-profile-id'];
				}
				// or select (set) new one
				else
				{
					// find default one
					foreach ($paymentProfiles as $paymentProfile)
					{
						if ($paymentProfile['is_primary'] == 'Yes')
						{
							$paymentProfileId = $paymentProfile['usid'];
							break;
						}
					}
					
					// it default is not set - use first one
					if (!$paymentProfileId)
					{
						$selectedPaymentProfileId = $paymentProfiles[0]['usid'];
					}
				}
			}
			else
			{
				$paymentProfiles = false;
			}
		
			if ($paymentProfileId != 'billing' && $paymentProfiles)
			{
				foreach ($paymentProfiles as $_paymentProfile)
				{
					if ($_paymentProfile['usid'] == $paymentProfileId)
					{
						$paymentProfileMethodId = $_paymentProfile["pid"];
						$user->updateBillingInfoFromPaymentProfile($_paymentProfile);
						$billingData = $user->getBillingInfo();
						break;
					}
				}
			}
			

			
			// Shipping Data
			$shippingAddressId = "billing";
			if ($user->auth_ok)
			{
				$primaryAddress = $user->getShippingAdressPrimary();
				if ($primaryAddress && isset($primaryAddress["usid"]))
				{
					$shippingAddressId = $primaryAddress["usid"];
					$order->updateShippingAddress($primaryAddress);
				}
			}
			
			$shippingMethodId = "0";
			if ($shippingAddressId == "billing")
			{
				$billingData = $user->getBillingInfo();
				$order->updateShippingAddress($billingData);		
			}
			
			$shippingAddressBook = $user->getShippingAddresses();
			$shippingAddress = $order->getShippingAddress();
			
			if ($shippingAddress["country_id"] == 0)
			{
				$shippingIsEmpty = true;
				$shippingAddress["country_id"] = "-1";
				$shippingAddress["state_id"] = "-1";
			}
			else
			{
				$shippingIsEmpty = false;
			}
			
			// Payment Methods
			if (!isset($payment)) $payment = new PAYMENT($db);
		
			// TODO: Moneybookers to work in OPC
			// Temporarily keeping moneybookers from being used in OPC
			$moneybookers_count = 0;	
			/**
			foreach ($payment->methods as $method)
			{
				if (in_array($method->id, array('moneybookers', 'moneybookersacc', 'moneybookersewallet'))) $moneybookers_count++;
			}
			**/
			
			view()->assign("payment_methods", $payment->methods);
			view()->assign("payment_methods_count", count($payment->methods) - $moneybookers_count);
			
			// add free payment method (when possible order is free)
			// case 1: we are 100% sure that order is free - subtotal = 0, no fees, no taxes, no shipping costs - when all items have free shipping OR are digital 
			// case 2: we don't know 100% that order is free - subtota = 1, no fess, no taxes, but shipping costs may be added depending on method selected
			$freePaymentMethod = new PAYMENT_METHOD(0, "http", "custom", "", "Free", $msg["billing"]["order_is_free_title"], $msg["billing"]["order_is_free_text"], "", "free", "");
			$freePaymentMethod->payment_url = $url_https."p=one_page_checkout";
			view()->assign("free_payment_method", $freePaymentMethod);		
			
			$paymentMethodId = isset($_SESSION["opc-payment-method-id"]) ? $_SESSION["opc-payment-method-id"] : "0";
			$order->recalcOrderData();
			$order->getOrderData();

			//tax-related for shipping
			$shippingTaxable = $settings["ShippingTaxable"];
			$shippingTaxRate = 0;
			$shippingTaxDescription = "";
			
			if ($shippingTaxable)
			{
				$shippingTaxClassId = $settings["ShippingTaxClassId"];
			
				if (array_key_exists($shippingTaxClassId, $order->taxRates))
				{
					$shippingTaxRate = $order->taxRates[$shippingTaxClassId]["tax_rate"];
					$shippingTaxDescription = $order->taxRates[$shippingTaxClassId]["description"];
				}
			}

			$orderData = array(
				"subtotalAmount" => $order->subtotalAmount,
				"subtotalAmountWithTax" => $order->subtotalAmountWithTax,
				"discountAmount" => $order->discountAmount,
				"promoDiscountAmount" => $order->promoDiscountAmount,
				// Promo Code calculations
				"promoDiscountValue" => $order->promoDiscountValue,
				"promoDiscountType" => $order->promoDiscountType,
				"promoType" => $order->promoType, // Global, Product, or Shipping
				"shippingAmount" => $order->shippingAmount,
				"handlingSeparated" => $settings["ShippingHandlingSeparated"],
				"handlingSeparatedWhenShippingNotFree" => $settings["ShippingHandlingSeparated"],
				"handlingSeparatedWhenShippingFree" => 1,
				"handlingAmount" => $order->getHandlingAmount(false, false),
				"handlingAmountWhenShippingNotFree" => $order->getHandlingAmount(false, false),
				"handlingAmountWhenShippingFree" => $order->getHandlingAmount(true, false),
				"handlingTaxable" => $order->handlingTaxable,
				"handlingTaxRate" => $order->handlingTaxRate,
				"handlingTaxDescription" => $order->handlingTaxDescription,
				"taxAmount" =>
					$order->taxAmount -
					($order->shippingTaxable ? $order->shippingTaxAmount : 0) -
					($order->handlingTaxable ? $order->handlingTaxAmount : 0),
				"totalAmount" => $order->totalAmount,
				"shippingTaxable" => $shippingTaxable,
				"shippingTaxRate" => $shippingTaxRate,
				"shippingTaxDescription" => $shippingTaxDescription,
				"insureShipActive" => intval($order->insureShipActive),
				"insureShipAmount" => $order->insureShipAmount,
				"giftCertificateAmount" => $order->gift_cert_amount
			);

			$paymentMethods = array();
			foreach ($payment->methods as $key=>$value)
			{
				$paymentMethods["p".$key] = $value;
			}
			
			// Assign variables
			$opcData = array(
				"orderNumber" => intval($order->order_num),
				"orderData" => $orderData,
				"expressCheckout" => intval($user->express),
				"billingIsEmpty" => $billingIsEmpty, // || $additionalIsEmpty,  should not be here as additional moved under billing form
				"billingData" => $billingData,
				"paymentProfiles" => $paymentProfiles,
				"paymentProfileId" => $paymentProfileId,
				"paymentProfileMethodId" => $paymentProfileMethodId,
				"paymentMethodWay" => "card",
				"shippingIsFree" => intval($order->shippingIsFree),
				"shippingIsEmpty" => intval($shippingIsEmpty),
				"shippingShowAlternativeOnFree" => strtolower($settings["ShippingShowAlternativeOnFree"]) == "yes" ? true : false,
				"shippingIsDigital" => intval($order->shippingDigital),
				"shippingAddressId" => $shippingAddressId,
				"shippingMethodId" => $shippingMethodId,
				"shippingAddress" => $shippingAddress,
				"shippingAddressBook" => $shippingAddressBook,
				"shippingWithoutMethod" => strtolower($settings["ShippingWithoutMethod"]) == "yes",
				"shippingEnabled" => strtolower($settings["ShippingCalcEnabled"]) == "yes",
				"paymentMethodsCount" => count($payment->methods),
				"paymentMethods" => $paymentMethods,
				"paymentMethodId" => $paymentMethodId,
				"paymentFormValidatorJS" => '',
				"paymentError" => $payment->is_error ? $payment->error_message : false,
				"giftCertificateEnabled" => strtolower($settings['enable_gift_cert']) == "yes",
				"promoAvailable" => strtolower($settings["DiscountsPromo"]) == "yes",
				"promoCode" => isset($_SESSION["order_promo_code"]) ? $_SESSION["order_promo_code"] : "",
				"currentCurrency" => $_SESSION["admin_currency"],
				"displayPricesWithTax" => strtolower($settings["DisplayPricesWithTax"]) == "yes",
				"giftCertificate" => isset($_SESSION["order_gift_certificate"]) ? $_SESSION["order_gift_certificate"] : false,
				"currentCurrencyDecimalSymbol" => $settings["LocalizationCurrencyDecimalSymbol"],
				"currentCurrencySeparatingSymbol" => $settings["LocalizationCurrencySeparatingSymbol"],
				"giftMessageLength" => $settings["GiftCardMessageLength"]
			);
			
			view()->assign("opcData", json_encode($opcData));
			view()->assign("order", $order);
			view()->assign("auth_express", "yes");
			view()->assign("AllowCreateAccount", "yes");

?>
<script type="text/javascript" language="javascript">
var site_https_url = "<?= $settings["GlobalHttpsUrl"] ?>/index.php?";
var site_http_url = "<?= $settings["GlobalHttpUrl"] ?>/index.php?";
</script>
<?php
			require_once("content/classes/class.languages.php");
			$languages = new Language($db);
			$def_lang = $languages->getDefaultLanguage();
			$current_language = $languages->getActiveLanguageById($def_lang["language_id"]);

			view()->assign("msg", $msg = $languages->getDictionary($current_language["code"]));


			echo '<link href="content/cache/skins/'.escapeFileName($settings["DesignSkinName"]).'/styles/base.css" rel="stylesheet" type="text/css" />' .
				 '<script type="text/javascript" src="content/cache/languages/'.$current_language["code"].'.js"></script>' .
				 '<script type="text/javascript" src="content/cache/skins/'.escapeFileName($settings["DesignSkinName"]).'/javascript/global.js"></script>' .
				 '<script type="text/javascript" src="content/cache/skins/'.escapeFileName($settings["DesignSkinName"]).'/javascript/common.js"></script>' .
				 '<script type="text/javascript" src="content/cache/skins/'.escapeFileName($settings["DesignSkinName"]).'/javascript/validators.js"></script>' .
				 '<script type="text/javascript" src="content/vendors/jquery/autoresize/autoresize.jquery.min.js"></script>' .
				 '<script type="text/javascript" src="content/vendors/jquery/cookies/cookies.js"></script>' .
				 '<script type="text/javascript" src="content/cache/skins/'.escapeFileName($settings["DesignSkinName"]).'/javascript/opc.js"></script>';
			echo view()->fetch("templates/pages/checkout/opc.html");
?>
<script type="text/javascript" language="javascript">
$(function(){
	$("#cart-items-toggle").hide();
	$("#opc-login").hide();
	$("#opc-account").show();
	$("#opc-account .spacer").hide();
	$("#opc-account-inner").removeClass('invisible');
	$("#opc-account-inner").show();
	$("#cb-create-account").attr('checked', 'checked');
});
</script>
<?php
			$backurl = $settings["GlobalHttpsUrl"] . "/admin.php?p=order&oid=" . $order->order_num;
		}
	}
}
?>