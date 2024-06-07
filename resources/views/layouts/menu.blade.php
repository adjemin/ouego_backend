<!-- need to remove -->
<li class="nav-item">
    <a href="{{ route('home') }}" class="nav-link {{ Request::is('home') ? 'active' : '' }}">
        <i class="nav-icon fas fa-home"></i>
        <p>Home</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('services.index') }}" class="nav-link {{ Request::is('services*') ? 'active' : '' }}">
        <i class="nav-icon fas fa-home"></i>
        <p>Services</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('typeEngins.index') }}" class="nav-link {{ Request::is('typeEngins*') ? 'active' : '' }}">
        <i class="nav-icon fas fa-home"></i>
        <p>Type Engins</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('drivers.index') }}" class="nav-link {{ Request::is('drivers*') ? 'active' : '' }}">
        <i class="nav-icon fas fa-home"></i>
        <p>Drivers</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('enginPictures.index') }}" class="nav-link {{ Request::is('enginPictures*') ? 'active' : '' }}">
        <i class="nav-icon fas fa-home"></i>
        <p>Engin Pictures</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('products.index') }}" class="nav-link {{ Request::is('products*') ? 'active' : '' }}">
        <i class="nav-icon fas fa-home"></i>
        <p>Products</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('productTypes.index') }}" class="nav-link {{ Request::is('productTypes*') ? 'active' : '' }}">
        <i class="nav-icon fas fa-home"></i>
        <p>Product Types</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('productEnginRelations.index') }}" class="nav-link {{ Request::is('productEnginRelations*') ? 'active' : '' }}">
        <i class="nav-icon fas fa-home"></i>
        <p>Product Engin Relations</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('deliveryTypes.index') }}" class="nav-link {{ Request::is('deliveryTypes*') ? 'active' : '' }}">
        <i class="nav-icon fas fa-home"></i>
        <p>Delivery Types</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('carriers.index') }}" class="nav-link {{ Request::is('carriers*') ? 'active' : '' }}">
        <i class="nav-icon fas fa-home"></i>
        <p>Carriers</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('orders.index') }}" class="nav-link {{ Request::is('orders*') ? 'active' : '' }}">
        <i class="nav-icon fas fa-home"></i>
        <p>Orders</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('orderPickups.index') }}" class="nav-link {{ Request::is('orderPickups*') ? 'active' : '' }}">
        <i class="nav-icon fas fa-home"></i>
        <p>Order Pickups</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('orderDeliveries.index') }}" class="nav-link {{ Request::is('orderDeliveries*') ? 'active' : '' }}">
        <i class="nav-icon fas fa-home"></i>
        <p>Order Deliveries</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('orderItems.index') }}" class="nav-link {{ Request::is('orderItems*') ? 'active' : '' }}">
        <i class="nav-icon fas fa-home"></i>
        <p>Order Items</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('customers.index') }}" class="nav-link {{ Request::is('customers*') ? 'active' : '' }}">
        <i class="nav-icon fas fa-home"></i>
        <p>Customers</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('customerDevices.index') }}" class="nav-link {{ Request::is('customerDevices*') ? 'active' : '' }}">
        <i class="nav-icon fas fa-home"></i>
        <p>Customer Devices</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('invoices.index') }}" class="nav-link {{ Request::is('invoices*') ? 'active' : '' }}">
        <i class="nav-icon fas fa-home"></i>
        <p>Invoices</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('payments.index') }}" class="nav-link {{ Request::is('payments*') ? 'active' : '' }}">
        <i class="nav-icon fas fa-home"></i>
        <p>Payments</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('orderInvitations.index') }}" class="nav-link {{ Request::is('orderInvitations*') ? 'active' : '' }}">
        <i class="nav-icon fas fa-home"></i>
        <p>Order Invitations</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('transactions.index') }}" class="nav-link {{ Request::is('transactions*') ? 'active' : '' }}">
        <i class="nav-icon fas fa-home"></i>
        <p>Transactions</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('settings.index') }}" class="nav-link {{ Request::is('settings*') ? 'active' : '' }}">
        <i class="nav-icon fas fa-home"></i>
        <p>Settings</p>
    </a>
</li>
