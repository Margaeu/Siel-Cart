<?php

namespace App\Livewire\Customer;

use App\Models\Address;
use Livewire\Component;
use Illuminate\Support\Facades\Hash;

class Profile extends Component
{
    // Profile fields
    public $name;
    public $email;
    public $phone;
    public $date_of_birth;
    public $gender;

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
        $this->name = $customer->name;
        $this->email = $customer->email;
        $this->phone = $customer->phone;
        $this->date_of_birth = $customer->date_of_birth?->format('Y-m-d');
        $this->gender = $customer->gender;
    }

    public function updateProfile()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:customers,email,' . auth('customer')->id(),
            'phone' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
        ]);

        auth('customer')->user()->update([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth,
            'gender' => $this->gender,
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

    public function addAddress()
    {
        $this->reset([
            'editingAddressId',
            'address_full_name',
            'address_phone',
            'address_line_1',
            'address_line_2',
            'address_city',
            'address_state',
            'address_postal_code',
            'address_country',
            'address_is_default',
        ]);
        $this->showAddressForm = true;
    }

    public function editAddress($addressId)
    {
        $address = Address::where('id', $addressId)
            ->where('customer_id', auth('customer')->id())
            ->firstOrFail();

        $this->editingAddressId = $address->id;
        $this->address_full_name = $address->full_name;
        $this->address_phone = $address->phone;
        $this->address_line_1 = $address->address_line_1;
        $this->address_line_2 = $address->address_line_2;
        $this->address_city = $address->city;
        $this->address_state = $address->state;
        $this->address_postal_code = $address->postal_code;
        $this->address_country = $address->country;
        $this->address_is_default = $address->is_default;
        $this->showAddressForm = true;
    }

    public function saveAddress()
    {
        $this->validate([
            'address_full_name' => 'required|string|max:255',
            'address_phone' => 'required|string|max:255',
            'address_line_1' => 'required|string|max:255',
            'address_city' => 'required|string|max:255',
            'address_postal_code' => 'required|string|max:20',
            'address_country' => 'required|string|max:2',
        ]);

        $data = [
            'customer_id' => auth('customer')->id(),
            'full_name' => $this->address_full_name,
            'phone' => $this->address_phone,
            'address_line_1' => $this->address_line_1,
            'address_line_2' => $this->address_line_2,
            'city' => $this->address_city,
            'state' => $this->address_state,
            'postal_code' => $this->address_postal_code,
            'country' => $this->address_country,
            'is_default' => $this->address_is_default,
        ];

        if ($this->editingAddressId) {
            Address::where('id', $this->editingAddressId)
                ->where('customer_id', auth('customer')->id())
                ->update($data);
        } else {
            Address::create($data);
        }
        // If set as default, unset other default addresses
        if ($this->address_is_default) {
            Address::where('customer_id', auth('customer')->id())
                ->where('id', '!=', $this->editingAddressId)
                ->update(['is_default' => false]);
        }
        $this->showAddressForm = false;
        session()->flash('address_success', 'Address saved successfully!');
    }

    public function deleteAddress($addressId){
         Address::where('id', $addressId)
            ->where('customer_id', auth('customer')->id())
            ->delete();

        session()->flash('address_success', 'Address deleted successfully!');
    }

    public function cancelAddressForm()
    {
        $this->showAddressForm = false;
    }


    public function render()
    {
    $addresses = auth('customer')->user()->addresses;
        return view('livewire.customer.profile',[
            'addresses' => $addresses,
        ])
            ->layout('components.layouts.front-end-layout');
    }
}
