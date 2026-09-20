<?php

namespace App\Livewire\Customer;

use Livewire\Component;
use Illuminate\Support\Facades\Hash;

class Profile extends Component
{
    // Profile fields
    public $first_name;
    public $last_name;
    public $email;
    public $phone;

    // Password fields
    public $current_password;
    public $new_password;
    public $new_password_confirmation;
    // Address fields
    public $showAddressForm = false;
    public $editingAddressId = null;
    public $address_full_name;
    public $address_phone;
    public $address_line_1;
    public $address_line_2;
    public $address_city;
    public $address_state;
    public $address_postal_code;
    public $address_country = 'US';
    public $address_is_default = false;

    public function mount()
    {
        $customer = auth('customer')->user();
        $this->first_name = $customer->first_name;
        $this->last_name = $customer->last_name;
        $this->email = $customer->email;
        $this->phone = $customer->phone;
    }

    public function updateProfile()
    {
        $this->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:customers,email,' . auth('customer')->id(),
            'phone' => 'nullable|string|max:255',
        ]);

        auth('customer')->user()->update([
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
        ]);

        session()->flash('profile_success', 'Profile updated successfully!');
    }

    public function updatePassword()
    {
        $this->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed',
        ]);

        if (!Hash::check($this->current_password, auth('customer')->user()->password)) {
            session()->flash('password_error', 'Current password is incorrect');
            return;
        }

        auth('customer')->user()->update([
            'password' => Hash::make($this->new_password),
        ]);

        $this->reset(['current_password', 'new_password', 'new_password_confirmation']);
        session()->flash('password_success', 'Password updated successfully!');
    }

    public function render()
    {
        // Must use the storefront layout explicitly. Without it Livewire falls
        // back to config('livewire.layout') = components.layouts.app, which has
        // no header/footer and never includes partials.theme-styles — so
        // --color-primary is undefined and the avatar and submit buttons render
        // as white-on-white (present but invisible).
        return view('livewire.customer.profile')
            ->layout('components.layouts.front-end-layout', ['title' => 'My Profile - '.config('app.name')]);
    }
}
