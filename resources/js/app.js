import Alpine from 'alpinejs';
import {createIcons,Search,UserRound,Heart,ShoppingBag,Menu,Truck,Banknote,Ruler,MessageCircle,Camera,Globe} from 'lucide';
window.Alpine=Alpine;
Alpine.start();
createIcons({icons:{Search,UserRound,Heart,ShoppingBag,Menu,Truck,Banknote,Ruler,MessageCircle,Instagram:Camera,Facebook:Globe}});
document.addEventListener('click',event=>{const button=event.target.closest('[data-confirm]');if(button&&!window.confirm(button.dataset.confirm))event.preventDefault();});


window.trapDialogFocus=(event,dialog)=>{const links=[...dialog.querySelectorAll('a[href],button:not([disabled]),input,select,textarea,[tabindex="0"]')].filter(e=>e.offsetParent!==null);if(!links.length)return;const first=links[0],last=links.at(-1);if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus()}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus()}};
