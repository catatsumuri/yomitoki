import { useLang } from '@erag/lang-sync-inertia/react';
import { X } from 'lucide-react';
import { useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';

type Props = {
    value: string[];
    onChange: (tags: string[]) => void;
};

export function TagInput({ value = [], onChange }: Props) {
    const { __ } = useLang();
    const [inputValue, setInputValue] = useState('');
    const inputRef = useRef<HTMLInputElement>(null);

    function addTag(raw: string): void {
        const tag = raw.trim().toLowerCase().replace(/\s+/g, '-');

        if (tag === '' || value.includes(tag)) {
            return;
        }

        onChange([...value, tag]);
    }

    function handleKeyDown(event: React.KeyboardEvent<HTMLInputElement>): void {
        if (event.key === 'Enter' || event.key === ',') {
            event.preventDefault();
            addTag(inputValue);
            setInputValue('');
        } else if (
            event.key === 'Backspace' &&
            inputValue === '' &&
            value.length > 0
        ) {
            onChange(value.slice(0, -1));
        }
    }

    function removeTag(tag: string): void {
        onChange(value.filter((t) => t !== tag));
    }

    return (
        <div
            className="flex min-h-10 flex-wrap items-center gap-1.5 rounded-2xl border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50"
            onClick={() => inputRef.current?.focus()}
        >
            {value.map((tag) => (
                <Badge key={tag} variant="secondary" className="gap-1 pr-1">
                    #{tag}
                    <button
                        type="button"
                        onClick={(e) => {
                            e.stopPropagation();
                            removeTag(tag);
                        }}
                        className="ml-0.5 rounded-sm opacity-60 hover:opacity-100"
                    >
                        <X className="size-3" />
                    </button>
                </Badge>
            ))}
            <Input
                ref={inputRef}
                value={inputValue}
                onChange={(e) => setInputValue(e.target.value)}
                onKeyDown={handleKeyDown}
                onBlur={() => {
                    if (inputValue.trim()) {
                        addTag(inputValue);
                        setInputValue('');
                    }
                }}
                placeholder={value.length === 0 ? __('Add a tag…') : ''}
                className="h-auto min-w-20 flex-1 border-none bg-transparent p-0 shadow-none focus-visible:ring-0"
            />
        </div>
    );
}
