document.getElementById('scrollUp').addEventListener('click', function() {
    window.scrollBy({
        top: -window.innerHeight,
        behavior: 'smooth'
    });
});

document.getElementById('scrollDown').addEventListener('click', function() {
    window.scrollBy({
        top: window.innerHeight,
        behavior: 'smooth'
    });
});