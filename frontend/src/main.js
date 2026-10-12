import './assets/css/app.css'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon as fa } from '@fortawesome/vue-fontawesome'
import {
  faArrowDown,
  faArrowUp,
  faBars,
  faChevronLeft,
  faClock,
  faEye,
  faEyeSlash,
  faCircleUser,
  faGear,
  faHouse,
  faLock,
  faPalette,
  faPen,
  faPhone,
  faPlus,
  faPrint,
  faReceipt,
  faRightFromBracket,
  faShieldHalved,
  faTrash,
  faUsers,
  faUtensils,
  faVolumeHigh,
  faVolumeXmark,
  faXmark,
} from '@fortawesome/free-solid-svg-icons'
import { faFacebookF, faGoogle } from '@fortawesome/free-brands-svg-icons'

import { createApp, defineComponent, h } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'
import VueTelInput from 'vue-tel-input'
import HTBFormField from '@/components/HTBFormField.vue'
import toastPlugin from '@/utils/toast'
import 'vue-tel-input/vue-tel-input.css'
import httpPlugin from '@/utils/http'
import VueSweetalert2 from 'vue-sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';
import moment from 'moment'
import { useSettingsStore } from '@/stores/settings'
// Add icons to the library
library.add(faClock, faPhone, faPrint, faReceipt, faVolumeHigh, faVolumeXmark, faArrowDown, faArrowUp, faPen, faPlus, faTrash, faUtensils, faBars, faChevronLeft, faCircleUser, faEye, faEyeSlash, faGear, faHouse, faLock, faPalette, faRightFromBracket, faShieldHalved, faUsers, faXmark, faFacebookF, faGoogle)

const app = createApp(App)

// Register global plugins
app.use(VueTelInput)
app.component(HTBFormField)
app.use(createPinia())
app.use(router)
app.use(httpPlugin, { router })
app.use(toastPlugin)
app.use(VueSweetalert2)

app.config.globalProperties.$date = (date, options = { format: 'DD/MM/YYYY [at] h:mm a' }) => {
  return moment(date).format(options.format)
};

// Theme the app from the saved UI settings (applies the cached copy first, so there's no flash).
useSettingsStore().init()

app.mount('#app')
