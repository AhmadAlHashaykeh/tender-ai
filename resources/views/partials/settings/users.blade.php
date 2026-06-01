{{-- User Management --}}
<div class="sc-section">
    <div class="sc-section-header">
        <span class="sc-section-icon"><i data-lucide="users" class="icon-sm"></i></span>
        <div>
            <h3 class="sc-section-title">User Management</h3>
            <p class="sc-section-desc">Team accounts. Role-based access control is reserved for a future phase.</p>
        </div>
    </div>

    {{-- User table --}}
    <div class="sc-user-table-wrap">
        <div class="sc-user-search-bar">
            <i data-lucide="search" class="icon-sm sc-user-search-icon"></i>
            <input type="text" id="userSearch" class="sc-user-search" placeholder="Filter by name or email…">
        </div>

        <table class="sc-user-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Joined</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="userTableBody">
                @forelse ($users as $user)
                    <tr class="sc-user-row" data-name="{{ strtolower($user->name) }}" data-email="{{ strtolower($user->email) }}">
                        <td>
                            <div class="sc-user-cell">
                                <div class="sc-avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
                                <div>
                                    <p class="sc-user-name">{{ $user->name }}</p>
                                    <p class="sc-user-email">{{ $user->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="sc-user-date">{{ $user->created_at->format('M j, Y') }}</td>
                        <td>
                            <span class="sc-status-badge sc-status-badge--{{ $user->is_active ? 'active' : 'inactive' }}">
                                {{ $user->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td>
                            @if ($user->id !== auth()->id())
                                <form method="POST" action="{{ route('settings.users.toggle-status', $user) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="sc-btn sc-btn--xs sc-btn--ghost">
                                        {{ $user->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>
                            @else
                                <span class="sc-user-you">You</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="sc-empty-row">
                            <i data-lucide="users" class="icon-sm"></i>
                            No users found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Add user collapsible --}}
    <details class="sc-advanced sc-advanced--add-user" id="addUserDetails"
        @if($errors->has('name') || $errors->has('email') || $errors->has('password')) open @endif>
        <summary class="sc-advanced-toggle">
            <i data-lucide="chevron-right" class="sc-advanced-chevron icon-sm"></i>
            <i data-lucide="user-plus" class="icon-sm" style="margin-right:0.25rem;"></i>
            Add New User
        </summary>
        <div class="sc-advanced-content">
            <form method="POST" action="{{ route('settings.users.store') }}">
                @csrf
                <input type="hidden" name="_settings_tab" value="users">
                <div class="sc-form-grid">
                    <div class="sc-field">
                        <label class="sc-label" for="user_name">Full Name</label>
                        <input type="text" class="sc-input @error('name') sc-input--error @enderror"
                            id="user_name" name="name" value="{{ old('name') }}" required>
                        @error('name')<p class="sc-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="sc-field">
                        <label class="sc-label" for="user_email">Email Address</label>
                        <input type="email" class="sc-input @error('email') sc-input--error @enderror"
                            id="user_email" name="email" value="{{ old('email') }}" required>
                        @error('email')<p class="sc-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="sc-field">
                        <label class="sc-label" for="user_password">Password</label>
                        <input type="password" class="sc-input @error('password') sc-input--error @enderror"
                            id="user_password" name="password" required>
                        @error('password')<p class="sc-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="sc-field">
                        <label class="sc-label" for="user_password_confirmation">Confirm Password</label>
                        <input type="password" class="sc-input" id="user_password_confirmation"
                            name="password_confirmation" required>
                    </div>
                </div>
                <div class="sc-form-actions">
                    <button type="submit" class="sc-btn sc-btn--primary">
                        <i data-lucide="user-plus" class="icon-sm"></i> Create User
                    </button>
                </div>
            </form>
        </div>
    </details>
</div>
