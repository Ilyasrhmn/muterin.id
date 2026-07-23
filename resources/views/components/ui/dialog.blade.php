<div id="Muterin-dialog" 
     class="fixed inset-0 z-[9999] hidden opacity-0 transition-opacity duration-300"
     role="dialog" 
     aria-modal="true"
     aria-labelledby="dialog-message">
    
    <!-- Backdrop with blur -->
    <div data-dialog-backdrop 
         class="absolute inset-0 bg-slate-900/60 backdrop-blur-md transition-all duration-300"></div>
    
    <!-- Dialog content wrapper -->
    <div class="relative flex items-center justify-center min-h-screen p-4">
        <div class="relative bg-surface rounded-2xl border border-border shadow-2xl w-full max-w-sm p-6 
                    transform scale-95 transition-all duration-300"
             data-dialog-content>
            
            <p id="dialog-message" 
               data-dialog-message 
               class="text-sm text-foreground font-medium"></p>
            
            <!-- Input fields -->
            <div data-dialog-fields class="mt-4 space-y-3 hidden">
                <label class="block space-y-1.5">
                    <span data-dialog-input-label 
                          class="text-xs font-medium text-muted-fg"></span>
                    <input data-dialog-input 
                           type="text"
                           class="w-full rounded-xl border border-border bg-surface px-3.5 py-2.5 text-sm 
                                  focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none
                                  transition-colors duration-200">
                </label>
                <label data-dialog-extra-wrap class="block space-y-1.5 hidden">
                    <span data-dialog-extra-label 
                          class="text-xs font-medium text-muted-fg"></span>
                    <textarea data-dialog-extra 
                              rows="2"
                              class="w-full rounded-xl border border-border bg-surface px-3.5 py-2.5 text-sm 
                                     focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none
                                     transition-colors duration-200"></textarea>
                </label>
            </div>
            
            <!-- Action buttons -->
            <div class="mt-6 flex justify-end gap-2">
                <button data-dialog-cancel 
                        type="button"
                        class="inline-flex items-center justify-center gap-2 font-heading font-semibold 
                               rounded-xl transition-all duration-200 text-sm px-4 py-2.5 
                               border border-border bg-surface text-foreground hover:bg-muted 
                               active:scale-95 cursor-pointer">
                    Batal
                </button>
                <button data-dialog-confirm 
                        type="button"
                        class="inline-flex items-center justify-center gap-2 font-heading font-semibold 
                               rounded-xl transition-all duration-200 text-sm px-4 py-2.5 
                               bg-primary text-white hover:bg-primary-hover 
                               active:scale-95 cursor-pointer 
                               disabled:opacity-50 disabled:pointer-events-none">
                    OK
                </button>
            </div>
        </div>
    </div>
</div>
