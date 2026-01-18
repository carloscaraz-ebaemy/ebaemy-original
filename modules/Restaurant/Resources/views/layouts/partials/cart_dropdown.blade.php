@php
    $configurationModel = \App\Models\Tenant\Configuration::first();
    $defaultImage = $configurationModel->product_default_image ?? 'imagen-no-disponible.jpg';
    $defaultImagePath = $defaultImage === 'imagen-no-disponible.jpg'
        ? asset('logo/imagen-no-disponible.jpg')
        : asset('storage/defaults/' . $defaultImage);
@endphp

<div class="dropdown cart-dropdown">
    <a href="#" class="dropdown-toggle" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" data-display="static">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-shopping-bag"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M6.331 8h11.339a2 2 0 0 1 1.977 2.304l-1.255 8.152a3 3 0 0 1 -2.966 2.544h-6.852a3 3 0 0 1 -2.965 -2.544l-1.255 -8.152a2 2 0 0 1 1.977 -2.304z" /><path d="M9 11v-5a3 3 0 0 1 6 0v5" /></svg>
        <span class="cart-count">0</span>
    </a>
    <div class="dropdown-menu">
        <div class="dropdownmenu-wrapper">

            <div class="dropdown-cart-products">

            </div><!-- End .cart-product -->

            <div class="dropdown-cart-total">
                <span>Total</span>

                <span class="cart-total-price">S/ 0</span>
            </div><!-- End .dropdown-cart-total -->

            <div class="dropdown-cart-action">
                <a  href="{{ route('restaurant.detail.cart') }}" class="btn">Ver Carrito</a>
                <!--<a href="#" class="btn">Checkout</a> -->
            </div><!-- End .dropdown-cart-total -->
        </div><!-- End .dropdownmenu-wrapper -->
    </div><!-- End .dropdown-menu -->
</div><!-- End .dropdown -->


@push('scripts')
<script type="text/javascript">

	function remove(id)
	{
		
		let array = localStorage.getItem('products_cart');
		array = JSON.parse(array);
		let indexFound = array.findIndex( x=> x.id == id)
		array.splice(indexFound, 1);
		localStorage.setItem('products_cart', JSON.stringify( array ) );
		populate();
		calculatetotal();
	
	}

	function calculatetotal()
	{
		let array = localStorage.getItem('products_cart');
		array = JSON.parse(array);
		let total = 0;
		array.forEach(element => {
			total += parseFloat(element.sale_unit_price)
		});

		$(".cart-total-price").empty();
		$(".cart-total-price").append(total.toFixed(2));

	}

	function populate()
	{
		$(".dropdown-cart-products").empty();
			$(".cart-count").empty();
			let count = 0;
			//get data local syrogare prodicts
			let array = localStorage.getItem('products_cart');
			array = JSON.parse(array)
			count = array.length;
			
			const defaultImagePath = '{{ $defaultImagePath }}';
				
			array.forEach(element => {
				const imagePath = (element.image_small && element.image_small !== 'imagen-no-disponible.jpg') 
					? `/storage/uploads/items/${element.image_small}` 
					: defaultImagePath;
				
				$(".dropdown-cart-products").append( `
						<div class="product">
							<div class="product-details">
							<h4 class="product-title">
								<a href="$">${element.description}</a>
							</h4>
							<span class="cart-product-info">
								<span class="cart-product-qty">1</span> x ${element.sale_unit_price}
							</span>
							</div>
							<figure class="product-image-container">
								<a href="#" class="product-image">
									<img alt="${element.description}" src="${imagePath}" />
								</a>
								<a href="#" onclick="remove(${element.id})" class="btn-remove" title="Remove Product">
									<i class="icon-cancel"></i>
								</a>
							</figure>
						</div>` 
					);
			});
			
			$(".cart-count").append(count);
	}

	
	$(function(){
    'use strict';
		populate();
		calculatetotal();
    });
</script>
@endpush