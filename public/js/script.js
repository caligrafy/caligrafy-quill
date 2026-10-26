/*
 * script.js - common page behaviors of the default Caligrafy views (no jQuery needed)
 * Author: Dory A.Azar
 *
 * - toggles the vertical menu on small screens
 * - smooth scrolling for links to "" (top) and to sections whose id starts with "kp"
 * - highlights the link of the section on screen
 * - social buttons demo
 */

document.addEventListener('DOMContentLoaded', function () {

    var menu = document.getElementById('vertical_menu');
    var menuIcon = document.getElementById('menu_icon');

    var isDisplayed = function (element) {
        return !!element && window.getComputedStyle(element).display !== 'none';
    };
    var isVisible = function (element) {
        return !!element && element.classList.contains('show');
    };
    var toggleMenu = function (element) {
        if (element) element.classList.toggle('show');
    };

    /* on window resize make sure the menu is closed */
    window.addEventListener('resize', function () {
        if (isVisible(menu)) toggleMenu(menu);
    });

    /* control the vertical menu on small screens */
    document.querySelectorAll('header').forEach(function (header) {
        header.addEventListener('click', function () {
            if (isDisplayed(menuIcon)) toggleMenu(menu);
        });
    });

    /* close the menu when the content is clicked */
    document.querySelectorAll('.wrapper, section, footer').forEach(function (element) {
        element.addEventListener('click', function (e) {
            if (isVisible(menu)) {
                toggleMenu(menu);
                e.preventDefault();
            }
        });
    });

    /* smooth scrolling for nav sections */
    document.querySelectorAll('a').forEach(function (anchor) {
        anchor.addEventListener('click', function (e) {
            var link = anchor.getAttribute('href');
            if (link === '') {
                e.preventDefault();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } else if (link && link.indexOf('#kp') === 0) {
                var target = document.querySelector(link);
                if (target) {
                    e.preventDefault();
                    window.scrollTo({ top: target.getBoundingClientRect().top + window.scrollY - 59, behavior: 'smooth' });
                }
            }
        });
    });

    /* identify what content is on screen */
    window.addEventListener('scroll', function () {
        var sections = Array.prototype.filter.call(document.querySelectorAll("div[id^='kp']"), function (section) {
            return section.getBoundingClientRect().top - 60 <= 0;
        });
        if (sections.length === 0) return;
        var current = sections[sections.length - 1].getAttribute('id');
        document.querySelectorAll('a.selected').forEach(function (a) { a.classList.remove('selected'); });
        document.querySelectorAll("a[href='#" + current + "']").forEach(function (a) { a.classList.add('selected'); });
    });

    /* social buttons demo */
    var socialItems = document.querySelectorAll('.social-class li');
    if (socialItems.length) {
        var allClasses = Array.prototype.map.call(socialItems, function (li) { return 'btn-' + li.dataset.code; });
        socialItems.forEach(function (li) {
            li.addEventListener('mouseenter', function () {
                var iconName = li.dataset.icon || li.dataset.code;
                var icon = "<i class='fa fa-" + iconName + "'></i>";
                document.querySelectorAll('.social-sizes .btn-social').forEach(function (b) { b.innerHTML = icon + 'Sign in with ' + li.dataset.name; });
                document.querySelectorAll('.social-sizes .btn-social-icon').forEach(function (b) { b.innerHTML = icon; });
                document.querySelectorAll('.social-sizes .btn').forEach(function (b) {
                    allClasses.forEach(function (c) { b.classList.remove(c); });
                    b.classList.add('btn-' + li.dataset.code);
                });
            });
        });
        socialItems[Math.floor(socialItems.length * Math.random())].dispatchEvent(new Event('mouseenter'));
    }
});
