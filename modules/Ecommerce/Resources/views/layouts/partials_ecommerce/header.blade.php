
<style>
#header_bar .header-menu {
    max-height: 300px !important;
    overflow:auto;
    overflow-y: auto;
}

#header_bar .header-menu::-webkit-scrollbar-track {
    -webkit-box-shadow: inset 0 0 6px rgba(0,0,0,0.1);
    background-color: #fdfdfd;
}

#header_bar .header-menu::-webkit-scrollbar {
    width: 6px;
    background-color: #fdfdfd;
}

#header_bar .header-menu::-webkit-scrollbar-thumb {
    background-color: #0187cc;
}

.header-dropdown a img {
    border-radius: 8px;
    padding: 4px;
}


.header-menu ul a {
    padding: 3px 6px;
}

.header-menu {
    box-shadow: 0 0 2px rgba(0,0,0,0.1);
    padding: 0 !important;
    border: none;
}

.header-menu a:hover, .header-menu a:focus {
    color: #0187cc;
    background-color: #f4f4f4;
}

.header-menu ul a {
    text-transform: capitalize !important;
}

.search_input {
    margin-bottom: 0.1rem;
    border-radius: 20px !important;
}

.search_input:focus {
    background-color: #fff;
    border-color: #fff;
    box-shadow: none;
}

.header-contact span {
    font-weight: normal;
}

div.cart-dropdown {
    display: flex;
    justify-content: center;
    align-items: center;
    background-color: transparent;
}

.header .dropdown-toggle {
    color: #fff;
    font-size: 10px;
    background-color: #1f1f39;
    height: 35px;
    display: flex;
    justify-content: center;
    align-items: center;
    border-radius: 20px;
    padding: 0 10px;
}

.dropdown-toggle .cart-count {
    background-color: transparent !important;
    color: white !important;
    margin-top: 12px;
    margin-right: 27px;
}

.search_input:focus {
    border: 1px solid var(--background-color) !important;
    background-color: transparent !important;
}

.search_input {
    width: 100%;
    height: 38px !important;
    border-radius: 20px !important;
    background-color: #eff0f6 !important;
}

.header-dropdown-inside {
    position: relative; 
}

.header-dropdown-inside .search-icon {
    position: absolute;
    left: 10px; 
    top: 50%;
    transform: translateY(-50%);
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.header-dropdown-inside .search_input {
    padding-left: 40px !important; 
    padding-right: 40px !important;
    width: 100%;
}

.header-dropdown-inside .clear-icon {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    width: 18px;
    height: 18px;
    cursor: pointer;
    display: none;
}
.header-dropdown-inside input:focus + .clear-icon,
.header-dropdown-inside input:not(:placeholder-shown) + .clear-icon {
    display: inline-block; /* Muestra el ícono */
}
/* -------------------------------------------------*/


 #header_bar .mobile-search-btn {
        display: none;
    }
    .close-search-btn {
    display: none; }
  


@media (max-width: 991px) {
      .close-search-btn {
    position: absolute;
    left: -25px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    font-size: 22px;
    cursor: pointer;
    display: none;
    z-index: 10;
    color: red;
}
  
}



    

@media (max-width: 768px){
   .session-text {
        
    display: none;
    }
    .header-contact img{
        width: 25px   !important   ;
        height: 25px !important;
        color: #fff
    }
    

    .header-dropdown {
        min-width: 100px !important;
    }
}
@media (max-width: 991px) {
      #header_bar.search-active .close-search-btn {
        display: block;
    }
  
    #header_bar .web-search-btn {
        display: none;
    }
    #header_bar .mobile-search-btn {
        display: block;
      
    }
    .mobile-search-btn{
    margin-left: auto;
    }
.header-center .header-dropdown {

    margin-left: 15px;
    position: relative;
    }


.header-contact-info-text { 
        display: none !important; 
    }

    /* Ajustes para el menú desplegable en dispositivos móviles */
     #header_bar.search-active {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 70px;
        background: #fff;
        z-index: 9999;
        display: flex;
        align-items: center;
        padding: 0 15px;
    }

    #header_bar.search-active .mobile-search-btn {
        display: none;
    }

    #header_bar.search-active .web-search-btn {
        display: flex !important;
        width: 100%;
        min-width: 100% !important;
    }

    /* Oculta logo y lado derecho cuando abre */
    .header-middle.search-open .header-left,
    .header-middle.search-open .header-right {
        display: none !important;
    }
}



 </style>

 <header class="header">

     <div class="header-middle">
         <div   class="container">
             <div class="header-left">
                <button class="mobile-menu-toggler text-dark" type="button">
                     <i class="icon-menu"></i>
                 </button>
                 <a href="{{ route("tenant.ecommerce.index") }}" class="logo" style="max-width: 180px">
                    @if(isset($information->logo))
                        <img src="{{ asset('storage/uploads/logos/'.$information->logo) }}" alt="Logo" />
                    @else
                        <img src="{{asset('logo/tulogo.png')}}" alt="Logo" />
                    @endif
                 </a>
             </div><!-- End .header-left -->
             
             
             <div id="header_bar" class="header-center header-dropdowns">

                <div class="mobile-search-btn " style="min-width: 40px;  justify-content: center; align-items: center;">
                <img src="{{ asset('images/search.svg') }}" alt="Buscar" class="search-icon" style="width: 25px; height: 25px;" >
                 </div>


                 <div class=" web-search-btn header-dropdown header-dropdown-inside" style="min-width:400px;">
                    <button class="close-search-btn" @click="closeMobileSearch">
                        ✕
                    </button>
                    <img src="{{ asset('images/search.svg') }}" alt="search" class="search-icon" style="width: 18px; height: 18px;">
                    {{-- <input placeholder="Buscar..." type="text" class="search_input form-control form-control-lg" v-model="value" v-on:keyup="autoComplete" @focus="isFocused = true" @blur="isFocused = false"/> --}}
                     <input placeholder="Buscar..." type="text" 
                     class="search_input form-control form-control-lg"
                      v-model="value" @input="autoComplete"  />
                    <img src="{{ asset('images/circle-xmark.svg') }}" alt="Clear" class="clear-icon" @click="clearInput">
                     <div class="header-menu">
                         <ul v-if="filteredResults.length > 0">
                            <li v-for="result in filteredResults" :key="result.id">
                                <a :href="'/ecommerce/item/' + result.id" class="d-flex">
                                    <div class="flex-grow-1"><img style="max-width: 80px" :src="result.image_url_small" alt="England flag">
                                    <span class="search_title" style="font-size: 1.0em;"> @{{ result.description }} </span>
                                    </div>
                                    <span class="search_price">@{{result.sale_unit_price}}</span>
                                    {{-- <div class="search_btn btn btn-default">@{{result.sale_unit_price}}</div> --}}
                                </a>
                            </li>
                         </ul>
                     </div><!-- End .header-menu -->
                 </div><!-- End .header-dropown -->


             </div><!-- End .headeer-center -->

             <div class="header-right">
                
                 
                 <div class="header-contact">
                     <span> Atención al</span>
                     <i class="fab fa-whatsapp"></i> <a href="#"><strong>{{$information->information_contact_phone}}</strong></a>
                 </div><!-- End .header-contact -->
                @include('ecommerce::layouts.partials_ecommerce.cart_dropdown')
                @include('ecommerce::partials.headers.session')

             </div><!-- End .header-right -->
         </div><!-- End .container -->
     </div><!-- End .header-middle -->

     <div class="header-bottom sticky-header">
        <div class="container d-flex">
            <nav class="main-nav flex-grow-1">

             </nav>
         </div><!-- End .header-bottom -->
     </div><!-- End .header-bottom -->
 </header><!-- End .header -->

 @push('scripts')
 <script>

document.addEventListener("DOMContentLoaded", function(){

    const mobileBtn = document.querySelector(".mobile-search-btn");
    const headerBar = document.getElementById("header_bar");
    const headerMiddle = document.querySelector(".header-middle");

    if(mobileBtn){
        mobileBtn.addEventListener("click", function(){
            headerBar.classList.add("search-active");
            headerMiddle.classList.add("search-open");

            setTimeout(() => {
                const input = headerBar.querySelector(".search_input");
                if(input) input.focus();
            }, 200);
        });
    }

});


new Vue({
    el: '#header_bar',

    data: {
        value: '',
        suggestions: [],
        resource: 'ecommerce'
    },

    created() {
        this.getItems();
    },

    computed: {

        // FILTRADO AUTOMÁTICO REACTIVO
        filteredResults() {

            if (!this.value.trim()) return [];

            const search = this.value.toLowerCase();

            return this.suggestions.filter(item => {
                const desc = (item.description || '').toLowerCase();
                const code = (item.internal_id || '').toLowerCase();
                return desc.includes(search) || code.includes(search);
            });

        }

    },

    methods: {

        getItems() {
            fetch(`/${this.resource}/items_bar`)
                .then(res => res.json())
                .then(data => {
                    this.suggestions = data.data || [];
                })
                .catch(error => {
                    console.error(error);
                });
        },

        autoComplete() {
            // Ya no necesitamos lógica aquí
            // Vue lo hace automáticamente con computed
        },

        clearInput() {
            this.value = '';
        },
        closeMobileSearch() {
        const headerBar = document.getElementById("header_bar");
        const headerMiddle = document.querySelector(".header-middle");

        headerBar.classList.remove("search-active");
        headerMiddle.classList.remove("search-open");

        this.value = '';
    }

    }
});
</script>


 @endpush