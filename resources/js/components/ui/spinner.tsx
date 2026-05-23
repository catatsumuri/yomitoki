import { Loader2Icon } from "lucide-react"
import { useLang } from "@erag/lang-sync-inertia/react"

import { cn } from "@/lib/utils"

function Spinner({ className, ...props }: React.ComponentProps<"svg">) {
  const { __ } = useLang()

  return (
    <Loader2Icon
      role="status"
      aria-label={__("Loading")}
      className={cn("size-4 animate-spin", className)}
      {...props}
    />
  )
}

export { Spinner }
