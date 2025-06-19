document.addEventListener('DOMContentLoaded', () => {
    // This is where you'd typically fetch data from a backend
    // For now, we'll use static placeholder data.
    const listingsData = [
        {
            id: 1,
            image: 'images/listing-textbook-accounting.jpg',
            name: 'Financial Accounting Textbook',
            price: 'R 350',
            seller: 'Lerato M.',
            location: 'Mahikeng Campus',
            description: 'Used Financial Accounting textbook, good condition. Required for ACC101.'
        },
        {
            id: 2,
            image: 'images/listing-laptop.jpg',
            name: 'HP Pavilion 15 (Used)',
            price: 'R 4,500',
            seller: 'Thabo N.',
            location: 'Mahikeng Campus',
            description: 'HP Pavilion 15 laptop, 8GB RAM, 256GB SSD. Perfect for student work.'
        },
        {
            id: 3,
            image: 'images/listing-tutoring.jpg',
            name: 'Maths Tutoring (First Year)',
            price: 'R 150/hr',
            seller: 'Palesa D.',
            location: 'Mmabatho Campus',
            description: 'Experienced tutor for first-year Mathematics. Online or in-person sessions.'
        },
        {
            id: 4,
            image: 'images/listing-mini-fridge.jpg',
            name: 'Mini Fridge for Res',
            price: 'R 700',
            seller: 'Sipho K.',
            location: 'Mmabatho Campus',
            description: 'Compact mini fridge, ideal for a student dorm room. Barely used.'
        },
        {
            id: 5,
            image: 'images/listing-guitar.jpg',
            name: 'Acoustic Guitar',
            price: 'R 900',
            seller: 'Zoe L.',
            location: 'Mahikeng Campus',
            description: 'Beginner acoustic guitar, good for learning. Comes with a soft case.'
        },
        {
            id: 6,
            image: 'images/listing-bike.jpg',
            name: 'Mountain Bike',
            price: 'R 1,200',
            seller: 'Neo M.',
            location: 'Mmabatho Campus',
            description: 'Sturdy mountain bike for getting around campus. Well-maintained.'
        }
    ];

    const listingsContainer = document.getElementById('listings-container');

    function createListingCard(listing) {
        const card = document.createElement('div');
        card.classList.add('listing-card');

        card.innerHTML = `
            <img src="${listing.image}" alt="${listing.name}">
            <div class="listing-card-content">
                <h3>${listing.name}</h3>
                <p class="price">${listing.price}</p>
                <p class="seller">Seller: ${listing.seller}</p>
                <p class="location">${listing.location}</p>
                <p class="description">${listing.description}</p>
                <a href="#" class="view-button">View Details</a>
            </div>
        `;
        return card;
    }

    function renderListings(data) {
        listingsContainer.innerHTML = ''; // Clear existing listings
        data.forEach(listing => {
            listingsContainer.appendChild(createListingCard(listing));
        });
    }

    // Initial render of featured listings
    renderListings(listingsData);

    // Example of a simple search/filter (client-side, for demonstration)
    const searchInput = document.querySelector('.search-bar input');
    const searchButton = document.querySelector('.search-bar button');

    if (searchInput && searchButton) {
        const performSearch = () => {
            const searchTerm = searchInput.value.toLowerCase();
            const filteredListings = listingsData.filter(listing =>
                listing.name.toLowerCase().includes(searchTerm) ||
                listing.description.toLowerCase().includes(searchTerm) ||
                listing.seller.toLowerCase().includes(searchTerm) ||
                listing.location.toLowerCase().includes(searchTerm)
            );
            renderListings(filteredListings);
        };

        searchButton.addEventListener('click', (e) => {
            e.preventDefault(); // Prevent form submission if it's part of a form
            performSearch();
        });

        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                performSearch();
            }
        });
    }

    // You can add more interactive elements here as needed,
    // e.g., smooth scrolling, form submissions, advanced filters.
});