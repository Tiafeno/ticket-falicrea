(function($) {
    $().ready(function () {
        
        const FilterbyDate = {
            template: '#filter-by-date',
            data: function() {
                return {
                    Years: [],
                    Month: ['Janvier', 'Fevrier', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet','Aout', 'Septembre',
                    'Octobre', 'November', 'Decembre'],
                    formInputs: {
                        month: 0,
                        year: 0
                    }
                }
            },
            mounted: function() {
                const objDate = new Date();
                this.formInputs.year = objDate.getFullYear(); // Set this year in filter
                this.formInputs.month = objDate.getMonth() + 1; // Set this month in filter
                this.Years = lodash.range(2020, objDate.getFullYear() + 1);
            },
            methods: {
                exportToExcel: function (type = 'xlsx', fn, dl) {
                    const currentDate = new Date();
                    const elt = document.getElementById('xls-downloaded');
                    const wb = XLSX.utils.table_to_book(elt, { sheet: "sheet1" });
                    return dl ?
                        XLSX.write(wb, { bookType: type, bookSST: true, type: 'binary' }) :
                        XLSX.writeFile(wb, fn || ( currentDate.toDateString() + '.' + (type || 'xlsx')));
                },
                findTraining: function(ev) {
                    ev.preventDefault();
                    this.$emit('filter', this.formInputs);
                }
            },
            delimiters: ['${', '}'],
        };
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
            components: {
                'comp-filter-date': FilterbyDate
            },
            data: function() {
                return {
                    loading: false,
                    axiosInstance: null,
                    total: 0,
                    postNew: apiSettings.product_post_new,
                    currency: apiSettings.currency,
                    products: [],
                    inputs: {
                        month: 0,
                        year: 0
                    }
                }
            },
            mounted: function () {
                const __date = new Date();
                this.inputs.year = __date.getFullYear(); // Set this year in filter
                this.inputs.month = __date.getMonth() + 1; // Set this month in filter
                this.initComponent();
            },
            methods: {
                initComponent: function() {
                    let data = new FormData();
                    data.append('action', 'action_former_details');
                    data.append('filter', `${this.inputs.month}|${this.inputs.year}`);
                    data.append('former_id', apiSettings.former_id);
                    this.loading = true;
                    this.$parent.axiosInstance.post('', data).then(resp => {
                        const response = lodash.clone(resp.data);
                        if(response.success) {
                            this.products = lodash.clone(response.data);
                            let _sum = lodash.map(this.products, p => parseInt(p.ttf));
                            this.total = lodash.sum(_sum);
                        }
                        this.loading = false;
                    });
                },
                filterDate: function (_filters) {
                    this.inputs = lodash.clone(_filters);
                    this.initComponent();
                }
            }
        };

        const TrainingDetails = {
            template: '#training-details',
            delimiters: ['${', '}'],
            components: {
                'comp-filter-date': FilterbyDate
            },
            data: function() {
                return {
                    loading: false,
                    TotalTTC: 0,
                    Product : null,
                    currency: apiSettings.currency,
                    items: [],
                    inputs: {
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
                    data.append('action', 'action_get_product_details');
                    data.append('filter', `${this.inputs.month}|${this.inputs.year}`);
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
                filterDate: function (_filters) {
                    this.inputs = lodash.clone(_filters);
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