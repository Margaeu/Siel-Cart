<?php

namespace App\Livewire;

use App\Models\Brand;
use App\Models\Product;
use Livewire\Component;
use App\Models\Category;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

class ProductListing extends Component
{
    use WithPagination;

    #[Url]
    public $category = '';
    #[Url]
    public $search = '';
    #[Url]
    public $brand = '';
    #[Url]
    public $minPrice = '';
    #[Url]
    public $maxPrice = '';
    #[Url]
    public $sort = 'newest';
    #[Url]
    public $featured = '';
    public $priceRange = [0,10000];

    public function mount(){
        //set the price range based on available products
        $maxProductPrice = Product::active()->max('price') ?? 10000;
        $this->priceRange = [0, ceil($maxProductPrice)];

        if(empty($this->maxPrice)){
            $this->maxPrice = $this->priceRange[1];
        }
    }

    public function updatingSearch(){
        $this->resetPage();
    }

    public function updatingCategory()
    {
        $this->resetPage();
    }

    public function updatingBrand()
    {
        $this->resetPage();
    }

    public function updatingSort()
    {
        $this->resetPage();
    }

    public function applyPriceFilter()
    {
        $this->resetPage();
    }

    public function clearFilters(){
        $this->reset(['search','category','brand','minPrice','maxPrice','featured']);
        $this->maxPrice = $this->priceRange[1];
        $this->resetPage();
    }


    public function render()
    {
        $query = Product::query()
        ->active()
        ->with(['category','brand','primaryImage']);

        //search
        if ($this->search) {
            $query->where(function($q){
                $q->where('name','like','%' . $this->search . '%')
                ->orWhere('description','like','%' . $this->search . '%')
                ->orWhere('sku','like','%' . $this->search . '%');
            });
        }

        //category
        if ($this->category) {
            $categoryModel = Category::where('slug', $this->category)->first();
            if ($categoryModel) {
                $query->where('category_id',$categoryModel->id);
            }
        }

        //brand filter
        if ($this->brand) {
            $brandModel = Brand::where('slug', $this->brand)->first();
            if ($brandModel) {
                $query->where('brand_id', $brandModel->id);
            }
        }

        //price range filter
        if ($this->minPrice !== '' || $this->maxPrice !== '') {
            $min = $this->minPrice ?: 0;
            $max = $this->maxPrice ?: $this->priceRange[1];
            $query->whereBetween('price',[$min, $max]);
        }

        //featured filter
        if ($this->featured) {
            $query->featured();
        }

        //sorting
        match ($this->sort) {
            'price_low' => $query->orderBy('price','asc'),
            'price_high' => $query->orderBy('price','desc'),
            'name_asc' => $query->orderBy('name','asc'),
            'name_desc' => $query->orderBy('name','desc'),
            'popular' => $query->orderBy('views_count','desc'),
            default => $query->latest()
        };

        $products = $query->paginate(12);

        $categories = Category::active()
        ->sorted()
        ->withCount('products')
        ->get();

        $brands = Brand::active()
        ->sorted()
        ->withCount('products')
        ->get();

        return view('livewire.product-listing',[
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands
        ])
        ->layout('components.layouts.front-end-layout');
    }
}
