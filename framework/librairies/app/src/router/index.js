import { createRouter, createWebHashHistory } from 'vue-router'
import HomePage from '../components/pages/HomePage.vue'

const router = createRouter({
  // hash history (#/page): the built app works from any folder (public/<name>/dist/) without a server fallback
  history: createWebHashHistory(),
  routes: [
    {
      path: '/',
      name: 'home',
      component: HomePage
    },
    // {
    //   path: '/about',
    //   name: 'about',
    //   // route level code-splitting
    //   // this generates a separate chunk (About.[hash].js) for this route
    //   // which is lazy-loaded when the route is visited.
    //   component: () => import('../views/AboutView.vue')
    // }
  ]
})

export default router
