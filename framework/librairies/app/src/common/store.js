/*
 * store.js - State Management with Pinia (the official Vue store)
 * Author: Dory Azar
 *
 * Use it in a component:
 *   import { useAppStore } from '@/common/store'
 *   const store = useAppStore()   // in setup(), or this.store = useAppStore() in data()/created()
 *   store.count, store.isEven, store.increment(1)
 */
import { defineStore } from 'pinia'

export const useAppStore = defineStore('app', {
    // shared data
    state: () => ({
        count: 0
    }),

    // computed values derived from the state
    getters: {
        isEven: (state) => state.count % 2 === 0
    },

    // methods that change the state, synchronous or asynchronous (API calls)
    actions: {
        increment(value = 1) {
            this.count += value;
        }
    }
});
