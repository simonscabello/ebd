import * as React from "react"

import { cn } from "@/lib/utils"

/**
 * <select> nativo estilizado. No celular abre o seletor do próprio sistema,
 * que é mais confortável do que um dropdown customizado.
 */
function NativeSelect({ className, children, ...props }: React.ComponentProps<"select">) {
  return (
    <select
      data-slot="native-select"
      className={cn(
        "border-input flex h-11 w-full appearance-none rounded-lg border bg-card bg-[length:1rem] bg-[right_0.75rem_center] bg-no-repeat pr-9 pl-3 text-base shadow-xs outline-none md:h-10 md:text-sm",
        "bg-[url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%23888%22 stroke-width=%222%22><path d=%22m6 9 6 6 6-6%22/></svg>')]",
        "focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:opacity-50",
        "aria-invalid:border-destructive",
        className
      )}
      {...props}
    >
      {children}
    </select>
  )
}

export { NativeSelect }
