(function($) {
    $().ready(function () {
        const Layout = {
            template: '#layout',
            delimiters: ['${', '}']
        };
        const Details = {
            template: '#details'
        };
        const routes = [
            {
                path: '/',
                component: Layout,
                redirect: '/details',
                children: [
                    {path: 'details', name: 'Details', component: Details},
                ],
            }
        ];
        var router = new VueRouter({
            routes // short for `routes: routes`
        });
        new Vue({
            el: '#former-details-app',
            router
        })
    });
})(jQuery);