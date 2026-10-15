import { defineComponent, h, type PropType, type SlotsType } from 'vue'
import { useStaticIsland, type StaticIsland as Island } from './useStaticIsland'

/** What useStaticIsland() returns (same name as the component). */
export type StaticIsland<T> = Island<T>

type SlotProps = { data: unknown; loading: boolean; error: unknown; load: Island<unknown>['load'] }

/**
 * `<StaticIsland url="/api/me/orders" when="visible">`: an island as a component.
 * The default slot gets `{ data, loading, error, load }`; `#fallback` shows until the data arrives.
 */
export const StaticIsland = defineComponent({
  name: 'StaticIsland',
  props: {
    url: { type: String, required: true },
    when: { type: String as PropType<'mount' | 'visible'>, default: 'mount' },
    buffer: { type: Number, default: 0 },
    as: { type: String, default: 'div' },
    init: { type: Object as PropType<RequestInit>, default: undefined },
    refreshAfterMutations: { type: Boolean, default: true },
  },
  slots: Object as SlotsType<{ default: SlotProps; fallback: SlotProps }>,
  setup(props, { slots }) {
    const island = useStaticIsland(props.url, {
      when: props.when,
      buffer: props.buffer,
      init: props.init,
      refreshAfterMutations: props.refreshAfterMutations,
    })

    return () => {
      const scope: SlotProps = {
        data: island.data.value,
        loading: island.loading.value,
        error: island.error.value,
        load: island.load,
      }
      const content = island.data.value === null && slots.fallback ? slots.fallback(scope) : slots.default?.(scope)

      return h(props.as, { ref: island.target }, content)
    }
  },
})
