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
                    loading: false,
                    axiosInstance: null,
                    postNew: apiSettings.product_post_new,
                    currency: apiSettings.currency,
                    products: [],
                }
            },
            mounted: function () {
                let data = new FormData();
                data.append('action', 'action_former_details');
                data.append('former_id', apiSettings.former_id);
                this.loading = true;
                this.$parent.axiosInstance.post('', data).then(resp => {
                    const response = lodash.clone(resp.data);
                    if(response.success) {
                        this.products = lodash.clone(response.data);
                    }
                    this.loading = false;
                });
            }
        };
        const TrainingDetails = {
            template: '#training-details',
            delimiters: ['${', '}'],
            data: function() {
                return {
                    loading: false,
                    TotalTTC: 0,
                    Product : null,
                    Month: ['Janvier', 'Fevrier', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet','Aout', 'Septembre',
                        'Octobre', 'November', 'Decembre'],
                    Years: [],
                    currency: apiSettings.currency,
                    items: [],
                    filters: {
                        month: 0,
                        year: 0
                    }
                }
            },
            mounted: function () {
               this.initComponent();
            },
            methods: {
                initComponent: function() {
                    let product_id = this.$route.params.id;
                    let data = new FormData();
                    let objDate = new Date();
                    this.Years = lodash.range(2020, objDate.getFullYear() + 1);
                    this.filters.year = objDate.getFullYear(); // Set this year in filter
                    data.append('action', 'action_get_product_details');
                    data.append('filter', `${this.filters.month}|${this.filters.year}`);
                    data.append('product_id', product_id);
                    this.loading = true;
                    this.$parent.axiosInstance.post('', data).then(resp => {
                        const response = lodash.clone(resp.data);
                        if(response.success) {
                            this.TotalTTC = lodash.sum(lodash.map(response.data, i => i.TTC));
                            this.items = lodash.clone(response.data);
                        }
                        this.loading = false;
                    });
                    const product = new wp.api.models.Product( {id: product_id });
                    product.fetch().done(prod => {
                        this.Product = lodash.clone(prod);
                    });
                },
                filterDate: function (evt) {
                    evt.preventDefault();
                    this.initComponent();
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