<?php

namespace App\Livewire\Customer;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class Profile extends Component
{
    // Profile fields
    public $first_name;

    public $last_name;

    public $email;

    public $phone;

    public $date_of_birth;

    // Identity verification for the profile form. Kept separate from
    // $current_password (below) so the two tabs' password prompts don't
    // bleed into each other.
    public $current_password_for_profile;

    // Email change. The address stays $email (current, verified) until the
    // customer clicks the confirmation link mailed to $pending_email — see
    // Customer::sendPendingEmailChangeNotification(). $new_email and
    // $current_password_for_email back the "Change" modal only; they are
    // never written straight to the customer, unlike the fields above.
    public $pending_email;

    public $new_email;

    public $current_password_for_email;

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
        $this->pending_email = $customer->pending_email;
        $this->phone = $customer->phone;
        // date_of_birth isn't cast (see Customer::casts()), so this is
        // already the raw 'Y-m-d' string the date_of_birth column stores.
        $this->date_of_birth = $customer->date_of_birth;
    }

    public function updateProfile()
    {
        $this->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => ['nullable', 'string', 'max:11', 'regex:/\A[0-9]+\z/'],
            // Nullable: some accounts predate this field or were edited by an
            // admin without one. When it is set, it must satisfy the same
            // 13-year minimum age CreateNewCustomer enforces at registration
            // — editing the birthday can't be used to slip under that floor.
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:'.now()->subYears(13)->format('Y-m-d')],
            // Identity verification: any profile edit must be confirmed with
            // the account's current password before it is written, the same
            // way a password change already is.
            'current_password_for_profile' => 'required',
        ], [
            'date_of_birth.before_or_equal' => 'You must be at least 13 years old.',
            'phone.max' => 'Phone numbers must contain no more than 11 digits.',
            'phone.regex' => 'Enter digits only for your phone number, for example 09171234567.',
            'current_password_for_profile.required' => 'Please enter your current password to confirm these changes.',
        ]);

        if (! Hash::check($this->current_password_for_profile, auth('customer')->user()->password)) {
            $this->addError('current_password_for_profile', 'Current password is incorrect.');

            return;
        }

        // Email is deliberately not written here — see requestEmailChange().
        auth('customer')->user()->update([
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth ?: null,
        ]);

        $this->reset('current_password_for_profile');
        session()->flash('profile_success', 'Profile updated successfully!');
        $this->dispatch('profile-updated');
    }

    public function cancelProfileVerification(): void
    {
        $this->reset('current_password_for_profile');
        $this->resetValidation('current_password_for_profile');
    }

    /**
     * Start an email change: verify the password, stash the new address on
     * pending_email, and mail a confirmation link to THAT address. The live
     * email (and its verified state) is untouched until the link is
     * clicked, mirroring how Google/GitHub handle it — see
     * ConfirmEmailChangeController.
     */
    public function requestEmailChange(): void
    {
        $this->validate([
            'new_email' => [
                'required',
                'email',
                'different:email',
                'unique:customers,email,'.auth('customer')->id(),
            ],
            'current_password_for_email' => 'required',
        ], [
            'new_email.different' => 'That is already your current email address.',
            'current_password_for_email.required' => 'Please enter your current password to confirm this change.',
        ]);

        if (! Hash::check($this->current_password_for_email, auth('customer')->user()->password)) {
            $this->addError('current_password_for_email', 'Current password is incorrect.');

            return;
        }

        $customer = auth('customer')->user();
        $customer->forceFill(['pending_email' => $this->new_email])->save();
        $customer->sendPendingEmailChangeNotification();

        $this->pending_email = $customer->pending_email;
        $this->reset(['new_email', 'current_password_for_email']);
        session()->flash('email_change_requested', "We've sent a confirmation link to {$this->pending_email}. Click it to finish changing your email — your current email stays active until then.");
        $this->dispatch('email-change-requested');
    }

    public function cancelEmailChangeRequest(): void
    {
        $this->reset(['new_email', 'current_password_for_email']);
        $this->resetValidation(['new_email', 'current_password_for_email']);
    }

    /**
     * Drop a pending change without ever confirming it — e.g. the customer
     * mistyped the address or changed their mind.
     */
    public function cancelPendingEmailChange(): void
    {
        auth('customer')->user()->update(['pending_email' => null]);
        $this->pending_email = null;
        session()->flash('profile_success', 'Email change cancelled.');
    }

    /**
     * Re-send the confirmation link for the existing pending_email. Throttled
     * per customer the same way Fortify throttles verification.send
     * (6 per minute), since this is another guest-reachable-by-anyone-who's-
     * logged-in mail trigger.
     */
    public function resendEmailChangeConfirmation(): void
    {
        $customer = auth('customer')->user();

        if (! $customer->pending_email) {
            return;
        }

        $throttleKey = 'email-change-resend:'.$customer->id;

        if (RateLimiter::tooManyAttempts($throttleKey, 6)) {
            session()->flash('email_change_error', 'Please wait a moment before requesting another confirmation email.');

            return;
        }

        RateLimiter::hit($throttleKey, 60);
        $customer->sendPendingEmailChangeNotification();

        session()->flash('email_change_requested', "We've sent another confirmation link to {$customer->pending_email}.");
    }

    public function updatePassword()
    {
        $this->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed',
        ]);

        if (! Hash::check($this->current_password, auth('customer')->user()->password)) {
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
