<a onclick="window.history.back()"  
    wire:navigate  
    class="shadow transition-all duration-100 inline-flex items-center content-center px-2 py-1 text-sm border border-blue-300 bg-white text-gray-600 rounded-full aspect-square hover:bg-blue-200 cursor-pointer waves-effect"
            x-data="{ isClicked: false }" 
            @click="isClicked = true; setTimeout(() => isClicked = false, 100)"
            style="transform:scale(1);"
            :style="isClicked ? 'transform:scale(0.7);' : ''"
    >
          <i class="fal fa-arrow-left"></i>
</a>
