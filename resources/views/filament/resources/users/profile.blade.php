@php
    $role = str($user->getRoleNames()->first() ?? 'User')->replace('_', ' ')->title();
    $name = $user->name ?: 'Unnamed user';
    $initials = $user->initials() ?: 'U';
@endphp

<article class="clsu-user-profile" aria-label="User profile for {{ $name }}">
    <section class="clsu-user-profile__identity" aria-label="Account identity">
        <div class="clsu-user-profile__avatar" aria-hidden="true">{{ $initials }}</div>

        <div class="clsu-user-profile__identity-copy">
            <p class="clsu-user-profile__eyebrow">Staff account</p>
            <h2 class="clsu-user-profile__name">{{ $name }}</h2>
            <a class="clsu-user-profile__email" href="mailto:{{ $user->email }}">{{ $user->email }}</a>
        </div>

        <div class="clsu-user-profile__badges" aria-label="Account role and status">
            <span class="clsu-user-profile__badge clsu-user-profile__badge--role">{{ $role }}</span>
            <span class="clsu-user-profile__badge {{ $user->is_active ? 'clsu-user-profile__badge--active' : 'clsu-user-profile__badge--inactive' }}">
                {{ $user->is_active ? 'Active' : 'Inactive' }}
            </span>
        </div>
    </section>

    <div class="clsu-user-profile__details">
        <section class="clsu-user-profile__card clsu-user-profile__card--personal" aria-labelledby="clsu-user-personal-heading">
            <div class="clsu-user-profile__card-heading">
                <h3 id="clsu-user-personal-heading">Profile information</h3>
                <p>Identity and access details</p>
            </div>

            <dl class="clsu-user-profile__fields">
                <div class="clsu-user-profile__field">
                    <dt>First name</dt>
                    <dd>{{ $user->first_name ?: 'Not provided' }}</dd>
                </div>
                <div class="clsu-user-profile__field">
                    <dt>Last name</dt>
                    <dd>{{ $user->last_name ?: 'Not provided' }}</dd>
                </div>
                <div class="clsu-user-profile__field clsu-user-profile__field--wide">
                    <dt>Email address</dt>
                    <dd><a href="mailto:{{ $user->email }}">{{ $user->email }}</a></dd>
                </div>
                <div class="clsu-user-profile__field clsu-user-profile__field--wide">
                    <dt>Assigned role</dt>
                    <dd>{{ $role }}</dd>
                </div>
            </dl>
        </section>
        
        <div class="clsu-user-profile__side">
            {{--
            <section class="clsu-user-profile__card" aria-labelledby="clsu-user-verification-heading">
                <div class="clsu-user-profile__card-heading">
                    <h3 id="clsu-user-verification-heading">Email verification</h3>
                </div>
                <div class="clsu-user-profile__verification {{ $user->email_verified_at ? 'clsu-user-profile__verification--complete' : '' }}">
                    <span class="clsu-user-profile__verification-mark" aria-hidden="true">{{ $user->email_verified_at ? '✓' : '!' }}</span>
                    <div>
                        <strong>{{ $user->email_verified_at ? 'Verified' : 'Not verified' }}</strong>
                        @if ($user->email_verified_at)
                            <p>Verified <time datetime="{{ $user->email_verified_at->toIso8601String() }}">{{ $user->email_verified_at->format('M d, Y · h:i A') }}</time></p>
                        @else
                            <p>No verification date recorded</p>
                        @endif
                    </div>
                </div>
            </section>
            --}}

            <section class="clsu-user-profile__card" aria-labelledby="clsu-user-history-heading">
                <div class="clsu-user-profile__card-heading">
                    <h3 id="clsu-user-history-heading">Record history</h3>
                </div>
                <dl class="clsu-user-profile__history">
                    <div>
                        <dt>Created</dt>
                        <dd>
                            @if ($user->created_at)
                                <time datetime="{{ $user->created_at->toIso8601String() }}">{{ $user->created_at->format('M d, Y · h:i A') }}</time>
                            @else
                                Not recorded
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Last updated</dt>
                        <dd>
                            @if ($user->updated_at)
                                <time datetime="{{ $user->updated_at->toIso8601String() }}">{{ $user->updated_at->format('M d, Y · h:i A') }}</time>
                            @else
                                Not recorded
                            @endif
                        </dd>
                    </div>
                </dl>
            </section>
        </div>
    </div>
</article>
