@php
    $role = str($user->getRoleNames()->first() ?? 'User')->replace('_', ' ')->title();
    $name = $user->name ?: 'Unnamed user';
@endphp

<article class="clsu-user-profile" aria-label="User profile for {{ $name }}">
    <header class="clsu-user-profile__header">
        <h2>{{ $name }}</h2>
        <span class="clsu-user-profile__status {{ $user->is_active ? 'clsu-user-profile__status--active' : 'clsu-user-profile__status--inactive' }}">
            {{ $user->is_active ? 'Active' : 'Inactive' }}
        </span>
    </header>

    <div class="clsu-user-profile__content">
        <section class="clsu-user-profile__section" aria-labelledby="clsu-user-details-heading">
            <h3 id="clsu-user-details-heading">Account details</h3>
            <dl class="clsu-user-profile__list">
                <div><dt>First name</dt><dd>{{ $user->first_name ?: 'Not provided' }}</dd></div>
                <div><dt>Last name</dt><dd>{{ $user->last_name ?: 'Not provided' }}</dd></div>
                <div><dt>Email address</dt><dd><a href="mailto:{{ $user->email }}">{{ $user->email }}</a></dd></div>
                <div><dt>Assigned role</dt><dd>{{ $role }}</dd></div>
            </dl>
        </section>

        <section class="clsu-user-profile__section clsu-user-profile__section--history" aria-labelledby="clsu-user-history-heading">
            <h3 id="clsu-user-history-heading">Record history</h3>
            <dl class="clsu-user-profile__list">
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
</article>
