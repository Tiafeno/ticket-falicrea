(function($) {
    $().ready(function () {
        const Layout = {
            template: '#layout',
            delimiters: ['${', '}'],
            data: function() {
                return {
                    axiosInstance: null,
                }
            },
            created: function () {
                this.axiosInstance = axios.create({
                    baseURL: apiSettings.ajaxurl,
                    headers: {
                        'X-WP-Nonce': apiSettings.nonce
                    }
                });
            }
        };
        const FormerDetails = {
            template: '#details',
            delimiters: ['${', '}'],
            data: function() {
                return {
                    axiosInstance: null,
                    postNew: apiSettings.product_post_new,
                    currency: apiSettings.currency,
                    products: [],
                }
            },
            mounted: function () {
                var self = this;
                var data = new FormData();
                data.append('action', 'action_former_details');
                data.append('former_id', apiSettings.former_id);
                this.$parent.axiosInstance.post('', data).then(function(resp) {
                    var response = lodash.clone(resp.data);
                    if(response.success) {
                        self.products = lodash.clone(response.data);
                    }
                });
            }
        };
        const TrainingDetails = {
            template: '#training-details',
            delimiters: ['${', '}'],
            data: function() {
                return {
                    TotalTTC: 0,
                    Product : null,
                    Month: [],
                    Years: [],
                    currency: apiSettings.currency,
                    items: [],
                    filters: {
                        month: 1,
                        year: 0
                    }
                }
            },
            mounted: function () {
                var self = this;
                var product_id = this.$route.params.id;
                var data = new FormData();
                var objDate = new Date();
                this.Month = ['Janvier', 'Fevrier', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 
                'Aout', 'Septembre', 'Octobre', 'November', 'Decembre'];
                this.Years = lodash.range(2020, objDate.getFullYear());
                this.filters.year = objDate.getFullYear();
                data.append('action', 'action_get_product_details');
                data.append('product_id', product_id);
                this.$parent.axiosInstance.post('', data).then(function(resp) {
                    var response = lodash.clone(resp.data);
                    if(response.success) {
                        self.TotalTTC = lodash.sum(lodash.map(response.data, i => i.totalTTC));
                        self.items = lodash.clone(response.data);
                    }
                });
                var product = new wp.api.models.Product( {id: product_id });
                product.fetch().done(function(prod) {
                    self.Product = lodash.clone(prod);
                });
            },
            methods: {
                filterDate: function (evt) {

                }
            }
        };

        const routes = [
            {
                path: '/',
                component: Layout,
                redirect: '/former',
                children: [
                    {
                        path: 'former',
                        name: 'FormerDetails',
                        component: FormerDetails
                    },
                    {
                        path: '/details/:id',
                        name: 'TrainingDetails',
                        component: TrainingDetails,
                    }
                ],
            },

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